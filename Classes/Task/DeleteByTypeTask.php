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
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\IndexQueue\InitializerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DeleteByType
 *
 * @package HMMH\SolrFileIndexer\Task
 */
class DeleteByTypeTask extends Command
{
    /**
     * @var int
     */
    protected int $siteRootPageId = 0;

    /**
     * @var string
     */
    protected string $type = MetadataRepository::FILE_TABLE;

    /**
     * @var bool
     */
    protected bool $reindexing = false;

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
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Delete data from Solr core')
            ->addOption(
                'root-page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Root page for cleanup'
            )
            ->addOption(
                'reindex',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Reindex data after cleanup',
                '0'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Type to remove, Default: sys_file_metadata',
                MetadataRepository::FILE_TABLE
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);

        $this->siteRootPageId = (int)$input->getOption('root-page');
        $this->type = (string)$input->getOption('type');
        $this->reindexing = (bool)$input->getOption('reindex');

        $this->setSite($this->siteRootPageId);

        try {
            $this->deleteByType($this->type);
            if ((bool)$this->reindexing === true) {
                $this->reindexByType($this->type);
            }
        } catch (\Exception $e) {
            // do nothing
        }

        return Command::SUCCESS;
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
