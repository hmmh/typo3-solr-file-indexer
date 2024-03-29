<?php

namespace HMMH\SolrFileIndexer\IndexQueue;

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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InitializerFactory
{
    /**
     * @param int    $rootPageId
     * @param string $indexingConfigurationName
     *
     * @return AbstractInitializer
     * @throws \Doctrine\DBAL\Exception
     */
    public static function createFileInitializerForRootPage(
        int $rootPageId,
        string $indexingConfigurationName = MetadataRepository::FILE_TABLE
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
