<?php

namespace HMMH\SolrFileIndexer\Resource;

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

use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexItemRepository
{
    const FILE_TABLE = 'tx_solrfileindexer_items';

    /**
     * @return void
     */
    public function lock()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
        $queryBuilder->update(self::FILE_TABLE)
            ->set(BaseUtility::getIndexItemEditlockField(), 1)
            ->executeStatement();
    }

    /**
     * @param array $item
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function save(array $item)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $item['root']),
            $queryBuilder->expr()->eq('item_uid', $item['item_uid']),
            $queryBuilder->expr()->eq('localized_uid', $item['localized_uid']),
            $queryBuilder->expr()->eq(BaseUtility::getIndexItemLanguageField(), $item['sys_language_uid']),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($item['indexing_configuration']))
        ];

        $uid = $queryBuilder->select('uid')
            ->from(self::FILE_TABLE)
            ->where(...$whereExpressions)
            ->executeQuery()
            ->fetchOne();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);

        if (!empty($uid)) {
            $queryBuilder->update(self::FILE_TABLE)
                ->set(BaseUtility::getIndexItemEditlockField(), 0)
                ->set('changed', $item['changed'])
                ->where(
                    $queryBuilder->expr()->eq('uid', $uid)
                )
                ->executeStatement();
        } else {
            $queryBuilder->insert(self::FILE_TABLE)
                ->values([
                    'root' => $item['root'],
                    'item_type' => MetadataRepository::FILE_TABLE,
                    'item_uid' => $item['item_uid'],
                    'localized_uid' => $item['localized_uid'],
                    BaseUtility::getIndexItemLanguageField() => $item['sys_language_uid'],
                    'indexing_configuration' => $item['indexing_configuration'],
                    'changed' => $item['changed']
                ])
                ->executeStatement();
        }
    }

    /**
     * @return \mixed[][]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findLockedEntries()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
        return $queryBuilder->select('*')
            ->from(self::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq(BaseUtility::getIndexItemEditlockField(), 1)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return void
     */
    public function removeObsoleteEntries()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
        $queryBuilder->delete(self::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq(BaseUtility::getIndexItemEditlockField(), 1)
            )
            ->executeStatement();
    }

    /**
     * @param int    $rootPage
     * @param string $type
     * @param string $indexingConfigurationName
     *
     * @return \mixed[][]
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItems(int $rootPage, string $type, string $indexingConfigurationName)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $rootPage),
            $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($type)),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($indexingConfigurationName)),
            $queryBuilder->expr()->eq(BaseUtility::getIndexItemEditlockField(), 0)
        ];

        return $queryBuilder->select('root', 'item_type', 'item_uid', 'indexing_configuration', 'changed')
            ->from(self::FILE_TABLE)
            ->where(...$whereExpressions)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int    $itemUid
     * @param int    $rootPage
     * @param int    $sysLanguageUid
     * @param string $type
     * @param string $configurationName
     *
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findIndexableItem(int $itemUid, int $rootPage, int $sysLanguageUid, string $type, string $configurationName)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $rootPage),
            $queryBuilder->expr()->eq('item_uid', $itemUid),
            $queryBuilder->expr()->eq(BaseUtility::getIndexItemLanguageField(), $sysLanguageUid),
            $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($type)),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($configurationName)),
            $queryBuilder->expr()->eq(BaseUtility::getIndexItemEditlockField(), 0)
        ];

        return $queryBuilder->select('*')
            ->from(self::FILE_TABLE)
            ->where(...$whereExpressions)
            ->executeQuery()
            ->fetchAssociative();
    }
}
