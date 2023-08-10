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

/**
 * Repository for accessing file collections stored in the database
 */
class FileCollectionRepository extends \TYPO3\CMS\Core\Resource\FileCollectionRepository
{

    /**
     * @param int      $rootPage
     * @param int|null $sysLanguageUid
     *
     * @return \TYPO3\CMS\Core\Collection\AbstractRecordCollection[]|null
     * @throws \Doctrine\DBAL\Exception
     */
    public function findForSolr(int $rootPage, ?int $sysLanguageUid = null)
    {
        $result = null;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);

        $conditions[] = $queryBuilder->expr()->inSet('use_for_solr', $rootPage);
        if ($sysLanguageUid !== null) {
            $conditions[] = $queryBuilder->expr()->in('sys_language_uid', [$sysLanguageUid, -1]);
        }

        $queryBuilder->select('*')
            ->from($this->table)
            ->where(...$conditions);

        $data = $queryBuilder->executeQuery()->fetchAllAssociative();
        if (!empty($data)) {
            $result = $this->createMultipleDomainObjects($data);
        }

        return $result;
    }
}
