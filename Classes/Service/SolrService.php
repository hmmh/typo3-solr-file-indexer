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
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Interfaces\ServiceInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SolrService
 *
 * @package HMMH\SolrFileIndexer\Service
 */
class SolrService implements ServiceInterface
{
    /**
     * Solr adapter
     *
     * @var ConnectionAdapter
     */
    protected $connectionAdapter;

    /**
     * Solr connection
     *
     * @var \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection
     */
    protected $solrConnection;

    /**
     * SolrService constructor.
     *
     * @throws NoSolrConnectionFoundException
     */
    public function __construct()
    {
        $this->connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);
        $extensionConfig = GeneralUtility::makeInstance(ExtensionConfig::class);
        $this->solrConnection = $this->connectionAdapter->getConnectionByPageId((int)$extensionConfig->getPageId());
    }

    /**
     * @param FileInterface $file
     * @return string
     */
    public function extractText(FileInterface $file): string
    {
        $localTempFilePath = $file->getForLocalProcessing(false);

        $query = GeneralUtility::makeInstance(\ApacheSolrForTypo3\Solr\Domain\Search\Query\ExtractingQuery::class, $localTempFilePath);
        $query->setExtractOnly(true);

        $response = $this->connectionAdapter->extractByQuery($this->solrConnection, $query);

        return (string)$response[0];
    }
}
