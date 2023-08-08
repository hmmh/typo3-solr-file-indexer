<?php

namespace HMMH\SolrFileIndexer\Resource;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function Symfony\Component\DependencyInjection\Loader\Configurator\expr;

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
            $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['origUid'], $item['t3_origuid']),
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
                    $GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['origUid'] => $item['t3_origuid'],
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

    public function removeObsoleteEntries()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_solrfileindexer_items');
        $queryBuilder->delete('tx_solrfileindexer_items')
            ->where(
                $queryBuilder->expr()->eq($GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['editlock'], 1)
            )
            ->executeStatement();
    }
}
