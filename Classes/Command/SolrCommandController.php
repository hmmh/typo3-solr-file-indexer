<?php
namespace HMMH\SolrFileIndexer\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de> hmmh
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

use Apache_Solr_HttpTransportException;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;

/**
 * SOLR Command Controller
 *
 * @package HMMH\SolrFileIndexer\Command
 *
 */
class SolrCommandController extends CommandController
{

    /**
     * @var \ApacheSolrForTypo3\Solr\ConnectionManager
     * @inject
     */
    protected $connectionManager;

    /**
     * The currently selected Site.
     *
     * @var Site
     */
    protected $site;

    /**
     * @param int    $siteRootPageId Site Root Page ID
     * @param string $type           Type (sys_file_metadata)
     * @param bool   $reindexing     Reindexing (0,1)
     *
     * @return void
     */
    public function deleteByTypeCommand($siteRootPageId, $type = 'sys_file_metadata', $reindexing = true)
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByPageId((int)$siteRootPageId);

        try {
            $this->deleteByType($type);
            if ((bool)$reindexing === true) {
                $this->reindexByType($type);
            }
        } catch (\Exception $e) {
            // do nothing
        }
    }

    /**
     * @param string $type
     *
     * @return void
     * @throws Apache_Solr_HttpTransportException
     */
    protected function deleteByType($type)
    {
        $solrServers = $this->connectionManager->getConnectionsBySite($this->site);
        foreach ($solrServers as $solrServer) {
            /* @var $solrServer SolrService */
            // make sure maybe not-yet committed documents are committed
            $solrServer->commit();
            $solrServer->deleteByType(trim($type), true);
        }
    }

    /**
     * @param string $type
     *
     * @return void
     */
    protected function reindexByType($type)
    {
        $itemIndexQueue = GeneralUtility::makeInstance(Queue::class);
        $itemIndexQueue->initialize($this->site, $type);
    }
}
