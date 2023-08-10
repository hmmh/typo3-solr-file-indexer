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

class MetadataRepository
{

    /**
     * @param int $uid
     * @param int $sysLanguageUid
     *
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findLocalizedEntry(int $uid, int $sysLanguageUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');

        $constraints = [
            $queryBuilder->expr()->eq(BaseUtility::getMetadataLanguageParentField(), $uid),
            $queryBuilder->expr()->eq(BaseUtility::getMetadataLanguageField(), $sysLanguageUid)
        ];

        return $queryBuilder->select('*')
            ->from('sys_file_metadata')
            ->where(...$constraints)
            ->executeQuery()
            ->fetchAssociative();
    }
}
