<?php
namespace HMMH\SolrFileIndexer\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh multimediahaus AG
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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Queue
 *
 * @package HMMH\SolrFileIndexer\IndexQueue
 */
class Queue extends \ApacheSolrForTypo3\Solr\IndexQueue\Queue
{
    const TABLE_INDEXQUEUE_ITEM = 'tx_solr_indexqueue_item';

    /**
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param array $rootPages
     */
    public function deleteItemsForDisabledRootpages($itemType, $itemUid, $rootPages)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_INDEXQUEUE_ITEM);
        $queryBuilder->delete(self::TABLE_INDEXQUEUE_ITEM)
            ->where(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($itemType, \PDO::PARAM_STR)),
                $queryBuilder->expr()->eq('item_uid', $queryBuilder->createNamedParameter($itemUid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->notIn('root', $queryBuilder->createNamedParameter($rootPages, Connection::PARAM_INT_ARRAY))
            )
            ->execute();
    }

    /**
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid  The item's uid, usually an integer uid, could be a
     *                         different value for non-database-record types.
     * @param int $rootPageId
     * @param string $indexingConfigurationName
     * @param array $indexingConfiguration
     *
     * @internal param array $rootPages
     */
    public function saveItemForRootpage($itemType, $itemUid, $rootPageId, $indexingConfigurationName, $indexingConfiguration)
    {
        $itemInQueueForRootPage = $this->containsItemWithRootPageId($itemType, $itemUid, $rootPageId, $indexingConfigurationName);
        if ($itemInQueueForRootPage) {
            $this->updateExistingItemForRootpage($itemType, $itemUid, $indexingConfigurationName, $rootPageId);
        } else {
            $this->addNewItemForRootpage($itemType, $itemUid, $indexingConfigurationName, $rootPageId, $indexingConfiguration);
        }
    }

    /**
     * @param array $items
     *
     * @return bool
     */
    public function addMultipleItemsToQueue(array $items)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_INDEXQUEUE_ITEM);
        foreach (array_chunk($items, 1000) as $chunk) {
            $connection->bulkInsert(
                self::TABLE_INDEXQUEUE_ITEM,
                $chunk,
                [
                    'root',
                    'item_type',
                    'item_uid',
                    'indexing_configuration',
                    'indexing_priority',
                    'changed',
                    'errors'
                ]
            );
        }

        return true;
    }

    /**
     * Updates an existing queue entry by $table, $uid and $rootPageId.
     *
     * @param string $itemType The item's table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfigurationName
     * @param int $rootPageId
     */
    protected function updateExistingItemForRootpage($itemType, $itemUid, $indexingConfigurationName, $rootPageId)
    {
        $data['changed'] = $this->getItemChangedTime($itemType, $itemUid);
        if (!empty($indexingConfigurationName)) {
            $data['indexing_configuration'] = $indexingConfigurationName;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_INDEXQUEUE_ITEM);
        $connection->update(
            self::TABLE_INDEXQUEUE_ITEM,
            $data,
            ['item_uid' => (int)$itemUid, 'item_type' => $itemType, 'root' => (int)$rootPageId]
        );
    }

    /**
     * Add a new queue entry by $table, $uid and $rootPageId.
     *
     * @param string $itemType The item's table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfigurationName
     * @param int $rootPageId
     * @param array $indexingConfiguration
     */
    protected function addNewItemForRootpage($itemType, $itemUid, $indexingConfigurationName, $rootPageId, $indexingConfiguration)
    {
        $indexRow = [
            'root' => $rootPageId,
            'item_type' => $itemType,
            'item_uid' => $itemUid,
            'indexing_configuration' => $indexingConfigurationName,
            'indexing_priority' => !empty($indexingConfiguration['indexingPriority']) ? (int)$indexingConfiguration['indexingPriority'] : 0,
            'changed' => $this->getItemChangedTime($itemType, $itemUid),
            'errors' => ''
        ];

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_INDEXQUEUE_ITEM);
        $connection->insert(
            self::TABLE_INDEXQUEUE_ITEM,
            $indexRow
        );
    }
}
