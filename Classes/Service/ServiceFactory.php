<?php
namespace HMMH\SolrFileIndexer\Service;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use HMMH\SolrFileIndexer\Base;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Interfaces\ServiceInterface;
use HMMH\SolrFileIndexer\Service\Tika\SolrService;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ServiceFactory
 *
 * @package HMMH\SolrFileIndexer\Service
 */
class ServiceFactory
{
    /**
     * @var SolrService
     */
    protected static $solrService = null;

    /**
     * @return \ApacheSolrForTypo3\Tika\Service\Tika\ServiceInterface|ServiceInterface
     * @throws NoSolrConnectionFoundException
     * @throws UnknownPackageException
     */
    public static function getTika()
    {
        if (self::$solrService === null) {
            $serviceFactory = new self();
            self::$solrService = $serviceFactory->getTikaService();
        }

        return self::$solrService;

    }

    /**
     * @return \ApacheSolrForTypo3\Tika\Service\Tika\ServiceInterface|ServiceInterface
     * @throws NoSolrConnectionFoundException
     * @throws UnknownPackageException
     */
    protected function getTikaService()
    {
        $extensionConfig = $this->getExtensionConfig();

        if ($extensionConfig->useTika()) {
            if ($this->isTikaActive()) {
                $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tika']);
                return $this->getTikaExtensionService($configuration['extractor']);
            }
            throw new UnknownPackageException('Package tika does not exists or is inactive');
        }

        return $this->getSolrService($extensionConfig);
    }

    /**
     * @return ExtensionConfig
     */
    protected function getExtensionConfig()
    {
        return Base::getObjectManager()->get(ExtensionConfig::class);
    }

    /**
     * @param $extensionConfig
     *
     * @return SolrService
     */
    protected function getSolrService($extensionConfig)
    {
        return Base::getObjectManager()->get(SolrService::class, $extensionConfig);
    }

    /**
     * @return bool
     */
    protected function isTikaActive()
    {
        $packageManager = Base::getObjectManager()->get(PackageManager::class);
        return $packageManager->isPackageActive('tika');
    }

    /**
     * @param string $tikaServiceType
     *
     * @return \ApacheSolrForTypo3\Tika\Service\Tika\ServiceInterface
     */
    protected function getTikaExtensionService($tikaServiceType)
    {
        return \ApacheSolrForTypo3\Tika\Service\Tika\ServiceFactory::getTika($tikaServiceType);
    }
}
