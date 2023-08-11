<?php

namespace HMMH\SolrFileIndexer\Service;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2023 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use HMMH\SolrFileIndexer\IndexQueue\InitializerFactory;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Resource\FileCollectionRepository;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ItemQueueWorker
{
    /** @var array  */
    protected array $items = [];

    /** @var SiteFinder */
    protected SiteFinder $siteFinder;

    /**
     * @param IndexItemRepository      $indexItemRepository
     * @param MetadataRepository       $metadataRepository
     * @param FileCollectionRepository $fileCollectionRepository
     * @param FrontendEnvironment      $frontendEnvironment
     */
    public function __construct(
        protected IndexItemRepository $indexItemRepository,
        protected MetadataRepository $metadataRepository,
        protected FileCollectionRepository $fileCollectionRepository,
        protected FrontendEnvironment $frontendEnvironment,
        protected QueueItemRepository $queueItemRepository
    ) {
        $this->siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function process(): void
    {
        $this->indexItemRepository->lock();

        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                $collections = $this->loadFilesFromCollections($site, $language);
                if (!empty($collections)) {
                    $this->generateItems($site, $language, $collections);
                }
            }
        }

        /** @var Queue $queue */
        $queue = GeneralUtility::makeInstance(Queue::class);

        foreach ($this->items as $item) {
            $this->indexItemRepository->save($item);
            $queue->saveItemForRootpage(
                MetadataRepository::FILE_TABLE,
                $item['item_uid'],
                $item['root'],
                $item['indexing_configuration'],
                []
            );
        }

        $garbageCollector = GeneralUtility::makeInstance(GarbageCollector::class);
        $garbageCollector->removeObsoleteEntriesFromIndexes();
    }

    /**
     * @param Site         $site
     * @param SiteLanguage $language
     *
     * @return \TYPO3\CMS\Core\Collection\AbstractRecordCollection[]|null
     * @throws \Doctrine\DBAL\Exception
     */
    protected function loadFilesFromCollections(Site $site, SiteLanguage $language)
    {
        $collections = $this->fileCollectionRepository->findForSolr($site->getRootPageId(), $language->getLanguageId());
        if (!empty($collections)) {
            foreach ($collections as $collection) {
                $collection->loadContents();
            }
        }
        return $collections;
    }

    /**
     * @param Site         $site
     * @param SiteLanguage $language
     * @param array        $collections
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function generateItems(Site $site, SiteLanguage $language, array $collections)
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($site->getRootPageId(), $language->getLanguageId());
        $indexingConfigurationNames = $solrConfiguration->getIndexQueueConfigurationNamesByTableName(MetadataRepository::FILE_TABLE);

        foreach ($indexingConfigurationNames as $indexingConfigurationName) {
            $fileInitializer = InitializerFactory::createFileInitializerForRootPage($site->getRootPageId(), $indexingConfigurationName);
            $allowedFileTypes = $fileInitializer->getArrayOfAllowedFileTypes();

            foreach ($collections as $collection) {
                foreach ($collection as $file) {
                    if (!in_array($file->getExtension(), $allowedFileTypes)) {
                        continue;
                    }
                    $metadata = $this->getMetadataFromFile($file);
                    if (empty($metadata)) {
                        continue;
                    }
                    $result = $this->prepareMetadata($language, $metadata);
                    $this->items[] = [
                        'root' => $site->getRootPageId(),
                        'item_uid' => $result['uid'],
                        'localized_uid' => $result['localized'],
                        'indexing_configuration' => $indexingConfigurationName,
                        'sys_language_uid' => $language->getLanguageId(),
                        'changed' => $result['changed']
                    ];
                }
            }
        }
    }

    /**
     * @param File|FileReference $file
     *
     * @return array|null
     */
    protected function getMetadataFromFile(mixed $file): ?array
    {
        if ($file instanceof File) {
            return $file->getMetaData()->get();
        } elseif ($file instanceof FileReference) {
            return $file->getOriginalFile()->getMetaData()->get();
        } else {
            return null;
        }
    }

    /**
     * @param SiteLanguage $language
     * @param array        $metadata
     *
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function prepareMetadata(SiteLanguage $language, array $metadata): array
    {
        $uid = $metadata['uid'];
        $localizedUid = 0;
        $changed = $metadata[BaseUtility::getMetadataTstampField()];

        if ($language->getLanguageId() > 0) {
            $result = $this->metadataRepository->findLocalizedEntry($uid, $language->getLanguageId());
            $localizedUid = $uid;
            if (!empty($result)) {
                $localizedUid = $result['uid'];
                $changed = $result[BaseUtility::getMetadataTstampField()];
            }
        }

        return [
            'uid' => $uid,
            'localized' => $localizedUid,
            'changed' => $changed
        ];
    }
}
