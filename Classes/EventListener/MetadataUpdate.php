<?php
namespace HMMH\SolrFileIndexer\EventListener;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2026 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\NoProcessingEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\AbstractDataUpdateEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordInsertedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Utility\BaseUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Re-queues files when their metadata is saved, translated or a translation is deleted.
 *
 * EXT:solr cannot resolve a site for sys_file_metadata (always pid 0) and would remove the file from the
 * index queue and the index. The responsible root pages are known from the file indexer items instead.
 * Deleting the default metadata record is left to EXT:solr (the file is removed via RemoveFile anyway).
 */
final class MetadataUpdate
{
    public function __construct(
        private readonly IndexItemRepository $indexItemRepository,
        private readonly Queue $queue,
        private readonly QueueItemRepository $queueItemRepository,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    #[AsEventListener(
        identifier: 'solr-file-indexer/metadata-updated',
        event: RecordUpdatedEvent::class,
        before: 'solr.index.updatehandler.noprocessingeventlistener',
    )]
    #[AsEventListener(
        identifier: 'solr-file-indexer/metadata-inserted',
        event: RecordInsertedEvent::class,
        before: 'solr.index.updatehandler.noprocessingeventlistener',
    )]
    #[AsEventListener(
        identifier: 'solr-file-indexer/metadata-deleted',
        event: RecordDeletedEvent::class,
        before: 'solr.index.updatehandler.noprocessingeventlistener',
    )]
    public function __invoke(AbstractDataUpdateEvent $event): void
    {
        if ($event->getTable() !== MetadataRepository::FILE_TABLE) {
            return;
        }
        // Monitoring disabled in EXT:solr: leave it to EXT:solr, which stops the event as well
        if ($this->extensionConfiguration->getMonitoringType() === NoProcessingEventListener::MONITORING_TYPE) {
            return;
        }

        $metadataUids = $this->getMetadataUids($event->getUid());
        // RecordDeletedEvent is dispatched before deletion, so the record (and its language parent) can still be read
        if ($event instanceof RecordDeletedEvent && count($metadataUids) === 1) {
            return;
        }

        $event->setStopProcessing(true);

        $queued = [];
        $itemUids = [];
        foreach ($metadataUids as $metadataUid) {
            foreach ($this->indexItemRepository->findByMetadataUid($metadataUid) as $item) {
                $key = $item['item_uid'] . '-' . $item['root'] . '-' . $item['indexing_configuration'];
                if (isset($queued[$key])) {
                    continue;
                }
                $this->queue->saveItemForRootpage(
                    MetadataRepository::FILE_TABLE,
                    $item['item_uid'],
                    $item['root'],
                    $item['indexing_configuration'],
                    []
                );
                $queued[$key] = true;
                $itemUids[] = (int)$item['item_uid'];
            }
        }

        // The default record did not change, so force re-indexing to drop the deleted translation from the index
        if ($event instanceof RecordDeletedEvent && $itemUids !== []) {
            $this->queueItemRepository->updateItemsChangedTime(
                time(),
                [],
                [],
                [MetadataRepository::FILE_TABLE],
                array_unique($itemUids)
            );
        }
    }

    /**
     * A new translation is not known to the file indexer items until the next item queue worker run,
     * so the items of the default language record are re-queued as well.
     *
     * @return int[]
     */
    private function getMetadataUids(int $uid): array
    {
        $uids = [$uid];
        $record = BackendUtility::getRecord(MetadataRepository::FILE_TABLE, $uid, BaseUtility::getMetadataLanguageParentField());
        $parentUid = (int)($record[BaseUtility::getMetadataLanguageParentField()] ?? 0);
        if ($parentUid > 0) {
            $uids[] = $parentUid;
        }

        return $uids;
    }
}
