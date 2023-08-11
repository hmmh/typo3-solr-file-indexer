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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\DataHandling\DataHandler;

class MoveMetadataToFileCollections implements UpgradeWizardInterface
{

    public function getTitle(): string
    {
        return 'Create FileCollections from Metadata';
    }

    public function getDescription(): string
    {
        return 'Migrate enable_indexing from sys_file_metadata to sys_file_collection';
    }

    public function executeUpdate(): bool
    {
        if (Environment::isCli() === false) {
            $request = (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
            Bootstrap::initializeBackendUser(BackendUserAuthentication::class, $request);
            Bootstrap::initializeBackendAuthentication();
            Bootstrap::initializeLanguageObject();
        }

        $success = true;

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
        $where = [
            $queryBuilder->expr()->neq('enable_indexing', $queryBuilder->createNamedParameter('')),
            $queryBuilder->expr()->eq(BaseUtility::getMetadataLanguageField(), 0)
        ];
        $result = $queryBuilder->select('file', 'enable_indexing')
            ->from(MetadataRepository::FILE_TABLE)
            ->where(...$where)
            ->executeQuery()
            ->fetchAllAssociative();

        $roots = [];
        foreach ($result as $metadata) {
            $rootPages = GeneralUtility::trimExplode(',', $metadata['enable_indexing']);
            foreach ($rootPages as $root) {
                $roots[$root][] = $metadata['file'];
            }
        }

        foreach ($roots as $rootPage => $fileUids) {
            try {
                $site = $siteFinder->getSiteByRootPageId($rootPage);

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_collection');
                $queryBuilder->insert('sys_file_collection')
                    ->values([
                        'pid' => $site->getRootPageId(),
                        'title' => 'autocreated-solr-file-indexer-' . (string)$rootPage,
                        'type' => 'static',
                        'use_for_solr' => (string)$rootPage,
                        'tstamp' => time(),
                        'crdate' => time()
                    ])
                    ->executeStatement();

                $collectionUid = $queryBuilder->getConnection()->lastInsertId();

                foreach ($fileUids as $fileUid) {
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $fileObject = $resourceFactory->getFileObject($fileUid);

                    $newId = 'NEW1234';
                    $data = [];
                    $data['sys_file_reference'][$newId] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $fileObject->getUid(),
                        'tablenames' => 'sys_file_collection',
                        'uid_foreign' => $collectionUid,
                        'fieldname' => 'assets',
                        'pid' => $site->getRootPageId()
                    ];
                    $data['sys_file_collection'][$collectionUid] = [
                        'files' => $newId
                    ];
                    /** @var DataHandler $dataHandler */
                    $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                    $dataHandler->start($data, []);
                    $dataHandler->process_datamap();

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
                    $queryBuilder->update(MetadataRepository::FILE_TABLE)
                        ->where($queryBuilder->expr()->eq('file', $fileUid))
                        ->set('enable_indexing', '')
                        ->executeStatement();

                    if (count($dataHandler->errorLog) !== 0) {
                        $success = false;
                    }
                }
            } catch (\Exception) {
                return false;
            }
        }

        return $success;
    }

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

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }
}
