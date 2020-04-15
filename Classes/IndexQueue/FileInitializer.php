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

use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileInitializer
 *
 * @package HMMH\SolrFileIndexer\IndexQueue
 */
class FileInitializer extends AbstractInitializer
{

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * FileInitializer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->queue = GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return mixed TRUE if initialization was successful, FALSE on error.
     */
    public function initialize()
    {
        $initialized = false;

        $indexRows = $this->getMetadataForSiteroot();

        if (!empty($indexRows)) {
            $initialized = $this->queue->addMultipleItemsToQueue($indexRows);
        }

        return $initialized;
    }

    /**
     * @return array
     */
    protected function getMetadataForSiteroot()
    {
        $indexRows = [];

        foreach ($this->getAllEnabledMetadata() as $metadata) {
            $enableIndexing = GeneralUtility::trimExplode(',', (string)$metadata['enable_indexing']);
            $siteroot = $this->site->getRootPageId();

            if (in_array($siteroot, $enableIndexing)) {
                $indexRows[] = [
                    'root' => $siteroot,
                    'item_type' => $this->type,
                    'item_uid' => (int)$metadata['uid'],
                    'indexing_configuration' => $this->indexingConfigurationName,
                    'indexing_priority' => $this->getIndexingPriority(),
                    'changed' => (int)$metadata['changed'],
                    'errors' => ''
                ];
            }
        }

        return $indexRows;
    }

    /**
     * @return array
     */
    protected function getAllEnabledMetadata()
    {
        $allowedFileTypes = $this->indexingConfiguration['allowedFileTypes'];

        $changedField = $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'];
        if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'])) {
            $changedField = 'GREATEST(' . $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'] . ',' .
                $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'] . ')';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');

        $constraints[] = $queryBuilder->expr()->neq('meta.enable_indexing', $queryBuilder->createNamedParameter('', \PDO::PARAM_STR));
        $constraints[] = $queryBuilder->expr()->eq('meta.file', $queryBuilder->quoteIdentifier('file.uid'));
        if (!empty($allowedFileTypes)) {
            $constraints[] = $queryBuilder->expr()->in('file.extension', $allowedFileTypes);
        }

        return $queryBuilder->select('meta.enable_indexing', 'meta.uid', 'meta.' . $changedField . ' as changed')
            ->from('sys_file_metadata', 'meta')
            ->from('sys_file', 'file')
            ->where(...$constraints)
            ->execute()
            ->fetchAll();
    }
}
