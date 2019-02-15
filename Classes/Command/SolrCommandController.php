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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use HMMH\SolrFileIndexer\Base;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
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
     * @var \HMMH\SolrFileIndexer\Service\ConnectionAdapter
     */
    protected $connectionAdapter;

    /**
     * The currently selected Site.
     *
     * @var Site
     */
    protected $site;

    /**
     * @param \HMMH\SolrFileIndexer\Service\ConnectionAdapter $connectionAdapter
     */
    public function injectConnectionAdapter(\HMMH\SolrFileIndexer\Service\ConnectionAdapter $connectionAdapter)
    {
        $this->connectionAdapter = $connectionAdapter;
    }

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
     * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
     */
    protected function deleteByType($type)
    {
        $solrConnection = $this->connectionAdapter->getConnectionByPageId($this->site->getRootPageId());
        $this->connectionAdapter->deleteByType($solrConnection, trim($type), true);
    }

    /**
     * @param string $type
     *
     * @return void
     */
    protected function reindexByType($type)
    {
        $itemIndexQueue = Base::getObjectManager()->get(QueueInitializationService::class);
        $itemIndexQueue->initializeBySiteAndIndexConfiguration($this->site, $type);
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
