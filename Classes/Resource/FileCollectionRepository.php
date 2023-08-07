<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace HMMH\SolrFileIndexer\Resource;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for accessing file collections stored in the database
 */
class FileCollectionRepository extends \TYPO3\CMS\Core\Resource\FileCollectionRepository
{

    /**
     * @param int $rootPage
     *
     * @return \TYPO3\CMS\Core\Collection\AbstractRecordCollection[]|\TYPO3\CMS\Core\Resource\Collection\AbstractFileCollection[]|null
     */
    public function findForSolr(int $rootPage)
    {
        $result = null;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);

        $queryBuilder->select('*')
            ->from($this->table)
            ->where($queryBuilder->expr()->inSet('use_for_solr', $rootPage));

        $data = $queryBuilder->executeQuery()->fetchAllAssociative();
        if (!empty($data)) {
            $result = $this->createMultipleDomainObjects($data);
        }

        return $result;
    }
}
