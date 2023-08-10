<?php

namespace HMMH\SolrFileIndexer\Service;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
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

        /** @var Queue $queue */
        $queue = GeneralUtility::makeInstance(Queue::class);

        foreach ($this->items as $item) {
            $this->indexItemRepository->save($item);
            $queue->saveItemForRootpage(
                'sys_file_metadata',
                $item['item_uid'],
                $item['root'],
                $item['indexing_configuration'],
                []
            );
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

    protected function collectGarbage()
    {
        $obsoleteEntries = $this->indexItemRepository->findLockedEntries();
        $connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);

        foreach ($obsoleteEntries as $entry) {
            $site = $this->siteFinder->getSiteByRootPageId($entry['root']);
            if (!empty($entry['localized_uid'])) {
                $itemUid = $entry['localized_uid'];
            } else {
                $itemUid = $entry['item_uid'];
            }

            $this->queueItemRepository->deleteItems([$site], [$entry['indexing_configuration']], [$entry['item_type']], [$itemUid]);

            $solrSite = $siteRepository->getSiteByPageId($entry['root']);
            $solrConfiguration = $solrSite->getSolrConfiguration();
            $enableCommitsSetting = $solrConfiguration->getEnableCommits();

            $solrConnections = $connectionAdapter->getConnectionsBySite($solrSite);
            foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
                if ($systemLanguageUid === $entry[$GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['languageField']]) {
                    $connectionAdapter->deleteByQuery($solrConnection, 'type:' . $entry['item_type'] . ' AND uid:' . intval($itemUid));
                    if ($enableCommitsSetting) {
                        $connectionAdapter->commit($solrConnection, false, false);
                    }
                }
            }
        }

        $this->indexItemRepository->removeObsoleteEntries();
    }
}
