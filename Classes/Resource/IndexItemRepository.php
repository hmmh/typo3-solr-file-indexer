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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexItemRepository
{

    /**
     * @return void
     */
    public function lock()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');
        $queryBuilder->update('tx_solrfileindexer_items')
            ->set($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 1)
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $item['root']),
            $queryBuilder->expr()->eq('item_uid', $item['item_uid']),
            $queryBuilder->expr()->eq('localized_uid', $item['localized_uid']),
            $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['languageField'], $item['sys_language_uid']),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($item['indexing_configuration']))
        ];

        $uid = $queryBuilder->select('uid')
            ->from('tx_solrfileindexer_items')
            ->where(...$whereExpressions)
            ->executeQuery()
            ->fetchOne();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');

        if (!empty($uid)) {
            $queryBuilder->update('tx_solrfileindexer_items')
                ->set($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 0)
                ->set('changed', $item['changed'])
                ->where(
                    $queryBuilder->expr()->eq('uid', $uid)
                )
                ->executeStatement();
        } else {
            $queryBuilder->insert('tx_solrfileindexer_items')
                ->values([
                    'root' => $item['root'],
                    'item_type' => 'sys_file_metadata',
                    'item_uid' => $item['item_uid'],
                    'localized_uid' => $item['localized_uid'],
                    $GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['languageField'] => $item['sys_language_uid'],
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');
        return $queryBuilder->select('*')
            ->from('tx_solrfileindexer_items')
            ->where(
                $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 1)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return void
     */
    public function removeObsoleteEntries()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');
        $queryBuilder->delete('tx_solrfileindexer_items')
            ->where(
                $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 1)
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $rootPage),
            $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($type)),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($indexingConfigurationName)),
            $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 0)
        ];

        return $queryBuilder->select('root', 'item_type', 'item_uid', 'indexing_configuration', 'changed')
            ->from('tx_solrfileindexer_items')
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');

        $whereExpressions = [
            $queryBuilder->expr()->eq('root', $rootPage),
            $queryBuilder->expr()->eq('item_uid', $itemUid),
            $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['languageField'], $sysLanguageUid),
            $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($type)),
            $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($configurationName)),
            $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 0)
        ];

        return $queryBuilder->select('*')
            ->from('tx_solrfileindexer_items')
            ->where(...$whereExpressions)
            ->executeQuery()
            ->fetchAssociative();
    }
}