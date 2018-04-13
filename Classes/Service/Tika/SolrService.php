<?php
namespace HMMH\SolrFileIndexer\Service\Tika;

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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\ExtractingQuery;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class SolrService
 *
 * @package HMMH\SolrFileIndexer\Service\Tika
 */
class SolrService
{
    /**
     * Solr connection
     *
     * @var \ApacheSolrForTypo3\Solr\SolrService
     */
    protected $solr;

    /**
     * SolrService constructor.
     *
     * @param ExtensionConfig $extensionConfig
     * @throws NoSolrConnectionFoundException
     */
    public function __construct(ExtensionConfig $extensionConfig)
    {
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $this->solr = $connectionManager->getConnectionByPageId($extensionConfig->getPageId());
    }

    /**
     * @param FileInterface $file
     * @return string
     */
    public function extractText(FileInterface $file)
    {
        $localTempFilePath = $file->getForLocalProcessing(false);
        $query = GeneralUtility::makeInstance(ExtractingQuery::class, $localTempFilePath);
        $query->setExtractOnly();

        $response = $this->solr->extractByQuery($query);

        if (PathUtility::basename($localTempFilePath) !== $file->getName()) {
            unlink($localTempFilePath);
        }

        return $response[0];
    }
}
