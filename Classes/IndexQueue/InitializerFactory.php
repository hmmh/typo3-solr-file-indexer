<?php

namespace HMMH\SolrFileIndexer\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InitializerFactory
{
    const CONFIGURATION_NAME = 'sys_file_metadata';

    /**
     * @param int    $rootPageId
     * @param string $indexingConfigurationName
     *
     * @return AbstractInitializer
     * @throws \Doctrine\DBAL\Exception
     */
    public static function createFileInitializerForRootPage(
        int $rootPageId,
        string $indexingConfigurationName = self::CONFIGURATION_NAME
    ): AbstractInitializer {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $solrSite = $siteRepository->getSiteByPageId($rootPageId);
        $solrConfiguration = $solrSite->getSolrConfiguration();
        $initializerClass = $solrConfiguration->getIndexQueueInitializerClassByConfigurationName($indexingConfigurationName);
        /** @var AbstractInitializer $fileInitializer */
        $fileInitializer = GeneralUtility::makeInstance($initializerClass);
        $fileInitializer->setSite($solrSite);
        $fileInitializer->setType($solrConfiguration->getIndexQueueTypeOrFallbackToConfigurationName($indexingConfigurationName));
        $fileInitializer->setIndexingConfigurationName($indexingConfigurationName);
        $fileInitializer->setIndexingConfiguration($solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName));

        return $fileInitializer;
    }
}
