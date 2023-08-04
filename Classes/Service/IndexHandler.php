<?php

namespace HMMH\SolrFileIndexer\Service;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use Doctrine\DBAL\ArrayParameterType;
use HMMH\SolrFileIndexer\IndexQueue\FileInitializer;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Resource\FileCollectionRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexHandler
{
    const FILE_TABLE = 'sys_file_metadata';

    protected Queue $queue;

    public function __construct()
    {
        $this->queue = GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * @param int $uid
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateMetadata(int $uid): void
    {
        $collectionRespository = GeneralUtility::makeInstance(FileCollectionRepository::class);

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();
        $this->queue = GeneralUtility::makeInstance(Queue::class);

        $record = (array)BackendUtility::getRecord(self::FILE_TABLE, $uid, '*', '', false);
        $rootPages = [];

        foreach ($sites as $site) {
            /** @var $site Site */
            $collections = $collectionRespository->findForSolr($site->getRootPageId());
            foreach ($collections as $collection) {
                $collection->loadContents();
            }
            foreach ($collections as $collection) {
                foreach ($collection as $file) {
                    /** @var \TYPO3\CMS\Core\Resource\File|\TYPO3\CMS\Core\Resource\FileReference $file */
                    if ($file instanceof \TYPO3\CMS\Core\Resource\File) {
                        $fileUid = $file->getUid();
                    } elseif ($file instanceof \TYPO3\CMS\Core\Resource\FileReference) {
                        $fileUid = $file->getOriginalFile()->getUid();
                    } else {
                        continue;
                    }
                    if ($fileUid === $record['file']) {
                        $rootPages[] = $site->getRootPageId();
                    }
                }
            }
        }

        if (empty($rootPages)) {
            $this->collectGarbage($uid);
        } else {
            $this->collectRecordGarbageForDisabledRootpages($uid, $rootPages);
            $this->updateItem($uid, $record, $rootPages);
        }
    }

    /**
     * @param int    $uid
     * @param array  $record
     * @param array  $rootPages
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function updateItem(int $uid, array $record, array $rootPages): void
    {
        $recordService = GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $frontendEnvironment = GeneralUtility::makeInstance(FrontendEnvironment::class);

        foreach ($rootPages as $rootPageId) {
            $solrConfiguration = $frontendEnvironment->getSolrConfigurationFromPageId($rootPageId);
            $indexingConfigurationName = $recordService->getIndexingConfigurationName(self::FILE_TABLE, $uid, $solrConfiguration);
            $indexingConfiguration = $solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName);

            $file = $this->getSysFile($record['file'], $indexingConfiguration);
            if (!$file) {
                $this->collectGarbage($uid);
                continue;
            }

            $this->queue->saveItemForRootpage(self::FILE_TABLE, $uid, $rootPageId, $indexingConfigurationName, $indexingConfiguration);
        }
    }

    /**
     * @param int $uid
     * @param array $indexingConfiguration
     *
     * @return mixed
     */
    protected function getSysFile(int $uid, array $indexingConfiguration)
    {
        $allowedFileTypes = FileInitializer::getArrayOfAllowedFileTypes($indexingConfiguration['allowedFileTypes']);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        $constraints[] = $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT));
        if ($allowedFileTypes !== []) {
            $constraints[] = $queryBuilder->expr()->in(
                'extension',
                $queryBuilder->createNamedParameter($allowedFileTypes, ArrayParameterType::STRING)
            );
        }

        $result = $queryBuilder->select('uid')
            ->from('sys_file')
            ->where(...$constraints)
            ->setMaxResults(1)
            ->execute();

        return $result->fetchAssociative();
    }

    /**
     * @param int    $uid
     * @param array  $rootPages
     *
     * @return void
     */
    protected function collectRecordGarbageForDisabledRootpages(int $uid, array $rootPages): void
    {
        $connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);

        $indexQueueItems = $this->queue->getItems(self::FILE_TABLE, $uid);
        foreach ($indexQueueItems as $indexQueueItem) {
            if (!in_array($indexQueueItem->getRootPageUid(), $rootPages)) {
                $site = $indexQueueItem->getSite();
                $solrConfiguration = $site->getSolrConfiguration();
                $enableCommitsSetting = $solrConfiguration->getEnableCommits();

                // a site can have multiple connections (cores / languages)
                $solrConnections = $connectionAdapter->getConnectionsBySite($site);
                foreach ($solrConnections as $connection) {
                    $connectionAdapter->deleteByQuery($connection, 'type:' . self::FILE_TABLE . ' AND uid:' . intval($uid));
                    if ($enableCommitsSetting) {
                        $connectionAdapter->commit($connection, false, false);
                    }
                }
            }
        }

        $this->queue->deleteItemsForDisabledRootpages(self::FILE_TABLE, $uid, $rootPages);
    }

    /**
     * @param int $fileUid
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteFile(int $fileUid): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
        $result = $queryBuilder->select('uid')
            ->from(self::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, \PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute();

        $metadata = $result->fetchAssociative();

        if (isset($metadata['uid'])) {
            $this->collectGarbage($metadata['uid']);
        }
    }

    /**
     * @param int    $uid
     *
     * @return void
     */
    public function collectGarbage(int $uid): void
    {
        $this->getGarbageHandler()->collectGarbage(self::FILE_TABLE, $uid);
    }

    /**
     * @return GarbageHandler
     */
    protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }
}