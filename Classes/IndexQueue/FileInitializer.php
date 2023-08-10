<?php
namespace HMMH\SolrFileIndexer\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh multimediahaus AG
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use HMMH\SolrFileIndexer\Resource\FileCollectionRepository;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Core\Resource\Collection\AbstractFileCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileInitializer
 *
 * @package HMMH\SolrFileIndexer\IndexQueue
 */
class FileInitializer extends AbstractInitializer
{
    /**
     * @var Queue
     */
    protected Queue $queue;

    /**
     * @var FileCollectionRepository
     */
    protected FileCollectionRepository $collectionRepository;

    /**
     * @param QueueItemRepository|null $queueItemRepository
     * @param PagesRepository|null     $pagesRepository
     */
    public function __construct(
        QueueItemRepository $queueItemRepository = null,
        PagesRepository $pagesRepository = null
    )
    {
        parent::__construct($queueItemRepository, $pagesRepository);
        $this->queue = GeneralUtility::makeInstance(Queue::class);
        $this->collectionRepository = GeneralUtility::makeInstance(FileCollectionRepository::class);
    }

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return mixed TRUE if initialization was successful, FALSE on error.
     */
    public function initialize(): bool
    {
        $initialized = false;

        try {
            $items = $this->getFileIndexerItems();
            $indexRows = $this->getIndexRows($items);

            if (!empty($indexRows)) {
                $initialized = $this->addMultipleItemsToQueue($indexRows);
            }
        } catch (\Exception) {
            // do nothing
        }

        return $initialized;
    }

    /**
     * @param array $indexRows
     *
     * @return bool
     */
    public function addMultipleItemsToQueue(array $indexRows): bool
    {
        return $this->queue->addMultipleItemsToQueue($indexRows);
    }

    /**
     * @param array $fileMetadata
     *
     * @return array
     */
    public function getIndexRows(array $items)
    {
        $indexRows = $itemUids = [];

        foreach ($items as $item) {
            if (!in_array($item['item_uid'], $itemUids)) {
                $indexRows[] = [
                    'root' => $item['root'],
                    'item_type' => $item['item_type'],
                    'item_uid' => $item['item_uid'],
                    'indexing_configuration' => $item['indexing_configuration'],
                    'indexing_priority' => $this->getIndexingPriority(),
                    'changed' => $item['changed'],
                    'errors' => ''
                ];

                $itemUids[] = $item['item_uid'];
            }
        }

        return $indexRows;
    }

    /**
     * In earlier versions $allowedFileTypes contained quotes. This is for backwards compatibility.
     */
    public function getArrayOfAllowedFileTypes(): array
    {
        preg_match_all('/\w+/u', $this->indexingConfiguration['allowedFileTypes'], $matches);
        return $matches[0] ?? [];
    }

    /**
     * @return \mixed[][]
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getFileIndexerItems()
    {
        /** @var IndexItemRepository $indexItemRepository */
        $indexItemRepository = GeneralUtility::makeInstance(IndexItemRepository::class);
        return $indexItemRepository->getItems($this->site->getRootPageId(), $this->type, $this->indexingConfigurationName);
    }
}
