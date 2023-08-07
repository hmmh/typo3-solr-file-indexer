<?php
namespace HMMH\SolrFileIndexer\Hook;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use HMMH\SolrFileIndexer\IndexQueue\FileInitializer;
use HMMH\SolrFileIndexer\Resource\FileCollectionRepository;
use HMMH\SolrFileIndexer\Service\IndexHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GarbageCollector
 *
 * @package HMMH\SolrFileIndexer\Hook
 */
class DataHandlerHook
{
    /**
     * Hooks into TCE Main and watches all record updates. If a change is
     * detected that would remove the record from the website, we try to find
     * related documents and remove them from the index.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data, not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array $fields,
        DataHandler $dataHandler
    ): void {
        if ($table === 'sys_file_metadata' && $status === 'update') {
            $indexHandler = GeneralUtility::makeInstance(IndexHandler::class);
            $indexHandler->updateMetadata($uid);
        }

        if ($table === 'sys_file_collection' && $status === 'update' && $this->hasRelevantFieldUpdates($fields)) {
            /** @var IndexHandler $indexHandler */
            $indexHandler = GeneralUtility::makeInstance(IndexHandler::class);
            foreach ($indexHandler->getAllSites() as $site) {
                $indexHandler->initializeBySite($site);
            }
        }

        if ($table === 'sys_file_collection' && $status === 'new') {
            if (str_starts_with((string)$uid, 'NEW')) {
                $uid = $dataHandler->substNEWwithIDs[$uid];
            }
            if (!empty($fields['use_for_solr'])) {
                $collectionRespository = GeneralUtility::makeInstance(FileCollectionRepository::class);
                $collection = $collectionRespository->findByUid($uid);
                $collection->loadContents();

                $rootPages = GeneralUtility::trimExplode(',', $fields['use_for_solr']);

                $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
                $indexingConfigurationName = 'sys_file_metadata';
                foreach ($rootPages as $rootPageUid) {
                    $solrSite = $siteRepository->getSiteByPageId((int)$rootPageUid);
                    $solrConfiguration = $solrSite->getSolrConfiguration();
                    $initializerClass = $solrConfiguration->getIndexQueueInitializerClassByConfigurationName($indexingConfigurationName);
                    /** @var AbstractInitializer $fileInitializer */
                    $fileInitializer = GeneralUtility::makeInstance($initializerClass);
                    $fileInitializer->setSite($solrSite);
                    $fileInitializer->setType($solrConfiguration->getIndexQueueTypeOrFallbackToConfigurationName($indexingConfigurationName));
                    $fileInitializer->setIndexingConfigurationName($indexingConfigurationName);
                    $fileInitializer->setIndexingConfiguration($solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName));
                    $files = $fileInitializer->getMetadataFromCollection($collection);
                    $indexRows = $fileInitializer->getIndexRows($files);
                    if (!empty($indexRows)) {
                        $fileInitializer->addMultipleItemsToQueue($indexRows);
                    }
                }
            }
        }
    }

    /**
     * @param array $fields
     *
     * @return bool
     */
    protected function hasRelevantFieldUpdates(array $fields): bool
    {
        $changedFieldsForUpdate = ['use_for_solr', 'type', 'folder_identifier', 'files', 'category'];
        foreach ($changedFieldsForUpdate as $property) {
            if (isset($fields[$property])) {
                return true;
            }
        }

        return false;
    }
}
