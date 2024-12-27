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

use HMMH\SolrFileIndexer\Service\ItemQueueWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeleteByType
 *
 * @package HMMH\SolrFileIndexer\Task
 */
class ItemQueueWorkerTask extends Command
{

    /**
     * @param ItemQueueWorker $itemQueueWorker
     */
    public function __construct(
        protected ItemQueueWorker $itemQueueWorker
    ) {
        parent::__construct();
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Worker for file indexing')
            ->addOption(
                'collections',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Comma separated list of Collection UIDs (Leave empty if all collections are to be initialized. Also, IDs of localized datasets must be specified.)',
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
        $collectionUids = null;
        $collectionString = (string)$input->getOption('collections') ?? '';
        if (!empty($collectionString)) {
            $collectionUids = array_map('intval', explode(',', $collectionString));
        }

        $this->itemQueueWorker->process($collectionUids);

        return Command::SUCCESS;
    }
}
