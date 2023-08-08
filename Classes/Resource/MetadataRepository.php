<?php

namespace HMMH\SolrFileIndexer\Resource;

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
