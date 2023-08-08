<?php
namespace HMMH\SolrFileIndexer\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Sascha Wilking <sascha.wilking@hmmh.de> hmmh
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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\IndexQueue\InitializerFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DeleteByType
 *
 * @package HMMH\SolrFileIndexer\Task
 */
class DeleteByTypeTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{
    /**
     * @var int
     */
    public $siteRootPageId = 0;

    /**
     * @var string
     */
    public $type = InitializerFactory::CONFIGURATION_NAME;

    /**
     * @var bool
     */
    public $reindexing = true;

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
     * This is the main method that is called when a task is executed
     * It MUST be implemented by all classes inheriting from this one
     * Note that there is no error handling, errors and failures are expected
     * to be handled and logged by the client implementations.
     * Should return TRUE on successful execution, FALSE on error.
     *
     * @return bool Returns TRUE on successful execution, FALSE on error
     */
    public function execute()
    {
        $this->connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);

        $this->setSite($this->siteRootPageId);

        try {
            $this->deleteByType($this->type);
            if ((bool)$this->reindexing === true) {
                $this->reindexByType($this->type);
            }
        } catch (\Exception $e) {
            // do nothing
        }

        return true;
    }

    /**
     * This method returns the destination mail address as additional information
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        return 'Site Root Page ID: ' . $this->siteRootPageId . ', Reindexing: ' . ($this->reindexing ? 'Yes' : 'No');
    }

    /**
     * @param string $type
     *
     * @return void
     * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
     */
    protected function deleteByType($type)
    {
        $solrConnections = $this->connectionAdapter->getConnectionsBySite($this->site);
        foreach ($solrConnections as $solrConnection) {
            $this->connectionAdapter->deleteByType($solrConnection, trim($type), true);
        }
    }

    /**
     * @param string $type
     *
     * @return void
     */
    protected function reindexByType($type)
    {
        $solrConfiguration = $this->site->getSolrConfiguration();
        $indexingConfigurationNames = $solrConfiguration->getIndexQueueConfigurationNamesByTableName($type);
        $queue = GeneralUtility::makeInstance(Queue::class);
        $queue->getInitializationService()->initializeBySiteAndIndexConfigurations($this->site, $indexingConfigurationNames);
    }

    /**
     * @param $siteRootPageId
     */
    protected function setSite($siteRootPageId)
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByPageId((int)$siteRootPageId);
    }
}
