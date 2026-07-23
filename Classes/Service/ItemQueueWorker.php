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
        protected QueueItemRepository $queueItemRepository,
        protected Queue $queue
    ) {
        $this->siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function process(?array $collectionUids): void
    {
        $this->indexItemRepository->lock($collectionUids);

        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                $collections = $this->getCollections($site, $language, $collectionUids);
                if (!empty($collections)) {
                    $this->generateItems($site, $language, $collections);
                }
            }
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
    protected function getCollections(Site $site, SiteLanguage $language, ?array $collectionUids = null)
    {
        return $this->fileCollectionRepository->findForSolr($site->getRootPageId(), $language->getLanguageId(), $collectionUids);
    }

    /**
     * @param Site         $site
     * @param SiteLanguage $language
     * @param \TYPO3\CMS\Core\Collection\AbstractRecordCollection[] $collections
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function generateItems(Site $site, SiteLanguage $language, array $collections)
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($site->getRootPageId(), $language->getLanguageId());
        $indexingConfigurationNames = $solrConfiguration->getIndexQueueConfigurationNamesByTableName(MetadataRepository::FILE_TABLE);
        $allowedFileTypesMap = [];

        // get the allowedFileTypes for each indexConfigurationName
        foreach ($indexingConfigurationNames as $indexingConfigurationName) {
            $fileInitializer = InitializerFactory::createFileInitializerForRootPage($site->getRootPageId(), $indexingConfigurationName);
            $allowedFileTypesMap[$indexingConfigurationName] = $fileInitializer->getArrayOfAllowedFileTypes();
        }

        foreach ($collections as $collection) {
            // load items of the collection being processed
            $collection->loadContents();

            foreach ($collection as $file) {
                // reinit metadata and result for every new file
                $metadata = null;
                $result = null;

                foreach ($allowedFileTypesMap as $indexingConfigurationName => $allowedFileTypes) {
                    if (!empty($allowedFileTypes) && !in_array($file->getExtension(), $allowedFileTypes)) {
                        continue;
                    }
                    $metadata ??= $this->getMetadataFromFile($file);
                    if (empty($metadata)) {
                        // skip the file processing entirely for all indexingConfigurations
                        continue 2;
                    }
                    $result ??= $this->prepareMetadata($language, $metadata);
                    $this->saveItem([
                        'root' => $site->getRootPageId(),
                        'item_uid' => $result['uid'],
                        'localized_uid' => $result['localized'],
                        'indexing_configuration' => $indexingConfigurationName,
                        'sys_language_uid' => $language->getLanguageId(),
                        'changed' => $result['changed'],
                        'collection' => $collection->getUid(),
                    ]);
                }
            }
        }
    }

    /**
     * @param array $item
     * @return void
     */
    protected function saveItem(array $item):void {
        $this->indexItemRepository->save($item);
        $this->queue->saveItemForRootpage(
            MetadataRepository::FILE_TABLE,
            $item['item_uid'],
            $item['root'],
            $item['indexing_configuration'],
            []
        );
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
