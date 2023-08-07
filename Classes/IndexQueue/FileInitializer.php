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

        $files = $this->getAllEnabledMetadata();

        $indexRows = $this->getIndexRows($files);

        if (!empty($indexRows)) {
            $initialized = $this->addMultipleItemsToQueue($indexRows);
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
     * @param AbstractFileCollection $collection
     *
     * @return array
     */
    public function getMetadataFromCollection(AbstractFileCollection $collection): array
    {
        $allowedFileTypes = self::getArrayOfAllowedFileTypes($this->indexingConfiguration['allowedFileTypes']);

        $files = [];

        foreach ($collection as $file) {
            /** @var \TYPO3\CMS\Core\Resource\File|\TYPO3\CMS\Core\Resource\FileReference $file */
            if (!in_array($file->getExtension(), $allowedFileTypes)) {
                continue;
            }
            if ($file instanceof \TYPO3\CMS\Core\Resource\File) {
                $metadata = $file->getMetaData()->get();
            } elseif ($file instanceof \TYPO3\CMS\Core\Resource\FileReference) {
                $metadata = $file->getOriginalFile()->getMetaData()->get();
            } else {
                continue;
            }
            $files[] = [
                'uid' => $metadata['uid'],
                'changed' => $metadata[$GLOBALS['TCA'][$this->type]['ctrl']['tstamp']]
            ];
        }

        return $files;
    }

    /**
     * @param array $files
     *
     * @return array
     */
    public function getIndexRows(array $files)
    {
        $indexRows = [];

        foreach ($files as $metadata) {
            $indexRows[] = [
                'root' => $this->site->getRootPageId(),
                'item_type' => $this->type,
                'item_uid' => (int)$metadata['uid'],
                'indexing_configuration' => $this->indexingConfigurationName,
                'indexing_priority' => $this->getIndexingPriority(),
                'changed' => (int)$metadata['changed'],
                'errors' => ''
            ];
        }

        return $indexRows;
    }

    /**
     * @return array
     */
    protected function getAllEnabledMetadata()
    {
        $files = [];

        $collections = $this->collectionRepository->findForSolr($this->site->getRootPageId());
        foreach ($collections as $collection) {
            $collection->loadContents();
        }

        foreach ($collections as $collection) {
            $files = array_merge($files, $this->getMetadataFromCollection($collection));
        }

        return $files;
    }

    /**
     * In earlier versions $allowedFileTypes contained quotes. This is for backwards compatibility.
     */
    public static function getArrayOfAllowedFileTypes(string $allowedFileTypes): array
    {
        preg_match_all('/\w+/u', $allowedFileTypes, $matches);
        return $matches[0] ?? [];
    }
}
