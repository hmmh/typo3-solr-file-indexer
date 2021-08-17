<?php
namespace HMMH\SolrFileIndexer\Hook;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Util;
use HMMH\SolrFileIndexer\Base;
use HMMH\SolrFileIndexer\IndexQueue\FileInitializer;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GarbageCollector
 *
 * @package HMMH\SolrFileIndexer\Hook
 */
class GarbageCollector extends \ApacheSolrForTypo3\Solr\GarbageCollector
{
    const FILE_TABLE = 'sys_file_metadata';

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * Hooks into TCE Main and watches all record updates. If a change is
     * detected that would remove the record from the website, we try to find
     * related documents and remove them from the index.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data, not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array $fields,
        /** @noinspection PhpUnusedParameterInspection */
        DataHandler $tceMain
    ) {
        parent::processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $tceMain);

        if ($table === self::FILE_TABLE && $status === 'update') {
            $this->queue = GeneralUtility::makeInstance(Queue::class);

            $record = (array)BackendUtility::getRecord($table, $uid, '*', '', false);
            $rootPages = empty($record['enable_indexing']) ? null : GeneralUtility::trimExplode(',', $record['enable_indexing']);

            if (empty($rootPages)) {
                $this->collectGarbage($table, $uid);
            } else {
                $this->collectRecordGarbageForDisabledRootpages($table, $uid, $rootPages);
                $this->updateItem($table, $uid, $record, $rootPages);
            }
        }
    }

    /**
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param array $record sys_file_metadata
     * @param array $rootPages
     */
    protected function updateItem($table, $uid, $record, $rootPages)
    {
        $recordService = GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);

        foreach ($rootPages as $rootPageId) {
            $solrConfiguration = Util::getSolrConfigurationFromPageId($rootPageId);
            $indexingConfigurationName = $recordService->getIndexingConfigurationName($table, $uid, $solrConfiguration);
            $indexingConfiguration = $solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName);

            $file = $this->getSysFile($record['file'], $indexingConfiguration);
            if (!$file) {
                $this->collectGarbage($table, $uid);
                continue;
            }

            $this->queue->saveItemForRootpage($table, $uid, $rootPageId, $indexingConfigurationName, $indexingConfiguration);
        }
    }

    /**
     * @param int $uid
     * @param array $indexingConfiguration
     *
     * @return mixed
     */
    protected function getSysFile($uid, $indexingConfiguration)
    {
        $allowedFileTypes = FileInitializer::getArrayOfAllowedFileTypes($indexingConfiguration['allowedFileTypes']);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        $constraints[] = $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT));
        if ($allowedFileTypes !== []) {
            $constraints[] = $queryBuilder->expr()->in('extension', $queryBuilder->createNamedParameter($allowedFileTypes, Connection::PARAM_STR_ARRAY));
        }

        return $queryBuilder->select('uid')
            ->from('sys_file')
            ->where(...$constraints)
            ->setMaxResults(1)
            ->execute()
            ->fetch();
    }

    /**
     * @param string $table
     * @param int $uid
     * @param array $rootPages
     */
    protected function collectRecordGarbageForDisabledRootpages($table, $uid, $rootPages)
    {
        $connectionAdapter = Base::getObjectManager()->get(ConnectionAdapter::class);

        $indexQueueItems = $this->queue->getItems($table, $uid);
        foreach ($indexQueueItems as $indexQueueItem) {
            if (!in_array($indexQueueItem->getRootPageUid(), $rootPages)) {
                $site = $indexQueueItem->getSite();
                $solrConfiguration = $site->getSolrConfiguration();
                $enableCommitsSetting = $solrConfiguration->getEnableCommits();

                // a site can have multiple connections (cores / languages)
                $solrConnections = $connectionAdapter->getConnectionsBySite($site);
                foreach ($solrConnections as $connection) {
                    $connectionAdapter->deleteByQuery($connection, 'type:' . $table . ' AND uid:' . intval($uid));
                    if ($enableCommitsSetting) {
                        $connectionAdapter->commit($connection, false, false);
                    }
                }
            }
        }

        $this->queue->deleteItemsForDisabledRootpages($table, $uid, $rootPages);
    }

    /**
     * @param $fileUid
     */
    public function deleteFile($fileUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
        $metadata = $queryBuilder->select('uid')
            ->from(self::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, \PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute()
            ->fetch();

        if (isset($metadata['uid'])) {
            $this->collectGarbage(self::FILE_TABLE, $metadata['uid']);
        }
    }
}
