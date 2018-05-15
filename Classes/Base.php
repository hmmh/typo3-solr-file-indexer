<?php
namespace HMMH\SolrFileIndexer;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh multimediahaus AG
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

use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extensionmanager\Utility\EmConfUtility;

/**
 * Class Base
 *
 * @package HMMH\SolrFileIndexer
 */
class Base
{
    /**
     * @var ObjectManagerInterface
     */
    protected static $objectManager;

    /**
     * get singleton object manager
     *
     * @return ObjectManagerInterface
     */
    public static function getObjectManager()
    {
        if (null === static::$objectManager) {
            static::$objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        }

        return static::$objectManager;
    }

    /**
     * @return string
     */
    public static function getSolrExtensionVersion()
    {
        $packageManager = Base::getObjectManager()->get(PackageManager::class);
        try {
            $package = $packageManager->getPackage('solr');
            return $package->getPackageMetaData()->getVersion();
        } catch (UnknownPackageException $e) {
            if (class_exists(EmConfUtility::class)) {
                $emConfUtility = GeneralUtility::makeInstance(EmConfUtility::class);
                $config = [
                    'key' => 'solr',
                    'siteRelPath' => 'typo3conf/ext/solr/'
                ];
                $emConf = $emConfUtility->includeEmConf($config);
                return $emConf['version'];
            }

            return '';
        }
    }
}
