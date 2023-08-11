<?php

namespace HMMH\SolrFileIndexer\Service;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GarbageCollector implements SingletonInterface
{

    public function __construct(
        protected IndexItemRepository $indexItemRepository,
        protected QueueItemRepository $queueItemRepository
    ) {}

    /**
     * @return void
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function removeObsoleteEntriesFromIndexes(): void
    {
        $obsoleteEntries = $this->indexItemRepository->findLockedEntries();
        $connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        foreach ($obsoleteEntries as $entry) {
            $site = $siteFinder->getSiteByRootPageId($entry['root']);
            if (!empty($entry['localized_uid'])) {
                $itemUid = $entry['localized_uid'];
            } else {
                $itemUid = $entry['item_uid'];
            }

            $this->queueItemRepository->deleteItems([$site], [$entry['indexing_configuration']], [$entry['item_type']], [$entry['item_uid']]);

            $solrSite = $siteRepository->getSiteByPageId($entry['root']);
            $solrConfiguration = $solrSite->getSolrConfiguration();
            $enableCommitsSetting = $solrConfiguration->getEnableCommits();

            $solrConnections = $connectionAdapter->getConnectionsBySite($solrSite);
            foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
                if ($systemLanguageUid === $entry[BaseUtility::getIndexItemLanguageField()]) {
                    $connectionAdapter->deleteByQuery($solrConnection, 'type:' . $entry['item_type'] . ' AND uid:' . intval($itemUid));
                    if ($enableCommitsSetting) {
                        $connectionAdapter->commit($solrConnection, false, false);
                    }
                }
            }
        }

        $this->indexItemRepository->removeObsoleteEntries();
    }

    /**
     * @param int $fileUid
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteFile(int $fileUid): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(MetadataRepository::FILE_TABLE);
        $result = $queryBuilder->select('uid')
            ->from(MetadataRepository::FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, \PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();

        if (!empty($result)) {
            foreach ($result as $metadata) {
                $this->collectGarbage($metadata['uid']);
            }
        }
    }

    /**
     * @param int    $uid
     *
     * @return void
     */
    protected function collectGarbage(int $uid): void
    {
        $this->getGarbageHandler()->collectGarbage(MetadataRepository::FILE_TABLE, $uid);
    }

    /**
     * @return GarbageHandler
     */
    protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }
}
