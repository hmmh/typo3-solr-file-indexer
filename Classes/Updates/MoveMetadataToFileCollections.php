<?php

namespace HMMH\SolrFileIndexer\Updates;

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

use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class MoveMetadataToFileCollections implements UpgradeWizardInterface
{

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Create FileCollections from Metadata';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Migrate enable_indexing from sys_file_metadata to sys_file_collection';
    }

    /**
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
     */
    public function executeUpdate(): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $result = $this->getMetadataToTransform();

        $roots = [];
        foreach ($result as $metadata) {
            $rootPages = GeneralUtility::trimExplode(',', $metadata['enable_indexing']);
            foreach ($rootPages as $root) {
                $roots[$root][] = $metadata['file'];
            }
        }

        $filesWithWrongRootpages = [];

        foreach ($roots as $rootPage => $fileUids) {
            try {
                $site = $siteFinder->getSiteByRootPageId($rootPage);
            } catch (SiteNotFoundException) {
                $filesWithWrongRootpages[] = $fileUids;
                continue;
            }
            $collection = $this->createNewFileCollection($rootPage);

            $fileCounter = 1;

            foreach ($fileUids as $fileUid) {
                $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                $fileObject = $resourceFactory->getFileObject($fileUid);

                $this->createFileReference($rootPage, $collection['uid'], $fileObject->getUid(), $fileCounter);

                $fileCounter++;

                $this->removeMetadataEntry($fileUid);
            }
        }

        foreach ($filesWithWrongRootpages as $fileUids) {
            foreach ($fileUids as $fileUid) {
                $this->removeMetadataEntry($fileUid);
            }
        }

        return empty($this->getMetadataToTransform());
    }

    /**
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
        $result = $queryBuilder->count('uid')
            ->from(MetadataRepository::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->neq('enable_indexing', $queryBuilder->createNamedParameter('')),
            )
            ->executeQuery()
            ->fetchOne();

        return $result > 0;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }

    /**
     * @return \mixed[][]
     * @throws \Doctrine\DBAL\Exception
     */
    private function getMetadataToTransform()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
        $where = [
            $queryBuilder->expr()->neq('enable_indexing', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->eq(BaseUtility::getMetadataLanguageField(), 0)
        ];
        return $queryBuilder->select('file', 'enable_indexing')
            ->from(MetadataRepository::FILE_TABLE)
            ->where(...$where)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int $rootPage
     *
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    private function createNewFileCollection(int $rootPage)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_collection');
        $queryBuilder->insert('sys_file_collection')
            ->values([
                'pid' => $rootPage,
                'title' => 'autocreated-solr-file-indexer-' . (string)$rootPage,
                'type' => 'static',
                'use_for_solr' => (string)$rootPage,
                'tstamp' => time(),
                'crdate' => time()
            ])
            ->executeStatement();

        $collectionUid = $queryBuilder->getConnection()->lastInsertId();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_collection');
        return $queryBuilder->select('*')
            ->from('sys_file_collection')
            ->where($queryBuilder->expr()->eq('uid', $collectionUid))
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @param int $rootPage
     * @param int $collectionUid
     * @param int $fileUid
     * @param int $fileCounter
     *
     * @return void
     */
    private function createFileReference(int $rootPage, int $collectionUid, int $fileUid, int $fileCounter): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->insert('sys_file_reference')
            ->values([
                'uid_local' => $fileUid,
                'tablenames' => 'sys_file_collection',
                'uid_foreign' => $collectionUid,
                'fieldname' => 'files',
                'pid' => $rootPage,
                'tstamp' => time(),
                'crdate' => time()
            ])
            ->executeStatement();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_collection');
        $queryBuilder->update('sys_file_collection')
            ->where($queryBuilder->expr()->eq('uid', $collectionUid))
            ->set('files', $fileCounter)
            ->executeStatement();
    }

    /**
     * @param int $fileUid
     *
     * @return void
     */
    private function removeMetadataEntry(int $fileUid): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
        $queryBuilder->update(MetadataRepository::FILE_TABLE)
            ->where($queryBuilder->expr()->eq('file', $fileUid))
            ->set('enable_indexing', '')
            ->executeStatement();
    }
}
