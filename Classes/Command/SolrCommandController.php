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
use HMMH\SolrFileIndexer\Base;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use ApacheSolrForTypo3\Solr\Site;

/**
 * SOLR Command Controller
 *
 * @package HMMH\SolrFileIndexer\Command
 *
 */
class SolrCommandController extends CommandController
{

    /**
     * @var \HMMH\SolrFileIndexer\Service\Adapter\SolrConnection
     * @inject
     */
    protected $solr;

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
        $this->setSite($siteRootPageId);

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
        $solrServers = $this->solr->getConnectionsBySite($this->site);
        foreach ($solrServers as $solrServer) {
            $this->solr->commit($solrServer);
            $this->solr->deleteByType($solrServer, trim($type), true);
        }
    }

    /**
     * @param string $type
     *
     * @return void
     */
    protected function reindexByType($type)
    {
        $itemIndexQueue = Base::getObjectManager()->get(Queue::class);
        $itemIndexQueue->initialize($this->site, $type);
    }

    /**
     * @param $siteRootPageId
     */
    protected function setSite($siteRootPageId)
    {
        $siteRepository = Base::getObjectManager()->get(SiteRepository::class);
        $this->site = $siteRepository->getSiteByPageId((int)$siteRootPageId);
    }
}
