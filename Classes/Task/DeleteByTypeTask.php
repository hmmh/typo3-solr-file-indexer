<?php
namespace HMMH\SolrFileIndexer\Task;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
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
        $this->type = trim((string)$input->getOption('type'));
        // "--reindex" without a value enables re-indexing, "--reindex=0|1" is still supported
        $reindexOption = $input->getOption('reindex');
        $this->reindexing = $reindexOption === null || (bool)filter_var($reindexOption, FILTER_VALIDATE_BOOLEAN);

        if ($this->siteRootPageId <= 0) {
            $output->writeln('<error>Please specify the root page of the site with --root-page</error>');
            return Command::FAILURE;
        }

        try {
            $this->setSite($this->siteRootPageId);
            $this->deleteByType($this->type);
            if ($this->reindexing) {
                $this->reindexByType($this->type);
            }
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Deleted documents of type "%s" for site "%s"', $this->type, $this->site->getLabel()));
        if ($this->reindexing) {
            $output->writeln('Index queue re-initialized, the documents are indexed with the next index queue worker run');
        } else {
            $output->writeln('<comment>No re-indexing: the documents are only indexed again when the records change, use --reindex to re-initialize the index queue</comment>');
        }

        return Command::SUCCESS;
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
        // Only delete the documents of this site, cores can be shared by several sites
        $query = 'type:' . $type . ' AND siteHash:"' . $this->site->getSiteHash() . '"';
        foreach ($solrConnections as $solrConnection) {
            $this->connectionAdapter->deleteByQuery($solrConnection, $query);
            $this->connectionAdapter->commit($solrConnection, false, false);
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
        GeneralUtility::makeInstance(QueueInitializationService::class)->initializeBySiteAndIndexConfigurations($this->site, $indexingConfigurationNames);
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
