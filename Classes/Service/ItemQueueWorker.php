<?php

namespace HMMH\SolrFileIndexer\Service;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use HMMH\SolrFileIndexer\IndexQueue\InitializerFactory;
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
        protected FrontendEnvironment $frontendEnvironment
    ) {
        $this->siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function process()
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

        foreach ($this->items as $item) {
            $this->indexItemRepository->save($item);
        }

        $this->collectGarbage();
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
        $indexingConfigurationNames = $solrConfiguration->getIndexQueueConfigurationNamesByTableName('sys_file_metadata');

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
                        't3_origuid' => $result['orig'],
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
        $orig = 0;
        $changed = $metadata[BaseUtility::getMetadataTstampField()];

        if ($language->getLanguageId() > 0) {
            $result = $this->metadataRepository->findLocalizedEntry($metadata['uid'], $language->getLanguageId());
            if (!empty($result)) {
                $orig = $uid;
                $uid = $result['uid'];
                $changed = $result[BaseUtility::getMetadataTstampField()];
            }
        }

        return [
            'uid' => $uid,
            'orig' => $orig,
            'changed' => $changed
        ];
    }

    protected function collectGarbage()
    {
        $obsoleteEntries = $this->indexItemRepository->findLockedEntries();

        foreach ($obsoleteEntries as $entry) {
            $this->getGarbageHandler()->collectGarbage('sys_file_metadata', $entry['item_uid']);
        }

        $this->indexItemRepository->removeObsoleteEntries();
    }

    protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }
}
