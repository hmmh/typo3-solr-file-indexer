<?php

declare(strict_types=1);

namespace HMMH\SolrFileIndexer\Tests\Unit\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordInsertedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use HMMH\SolrFileIndexer\EventListener\MetadataUpdate;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataUpdate::class)]
final class MetadataUpdateTest extends TestCase
{
    private IndexItemRepository&Stub $indexItemRepository;
    private Queue&MockObject $queue;
    private QueueItemRepository&MockObject $queueItemRepository;
    private ExtensionConfiguration&Stub $extensionConfiguration;
    private MetadataRepository&Stub $metadataRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexItemRepository = self::createStub(IndexItemRepository::class);
        $this->queue = $this->createMock(Queue::class);
        $this->queueItemRepository = $this->createMock(QueueItemRepository::class);
        $this->extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $this->extensionConfiguration->method('getMonitoringType')->willReturn(0);
        $this->metadataRepository = self::createStub(MetadataRepository::class);
    }

    private function getSubject(): MetadataUpdate
    {
        return new MetadataUpdate(
            $this->indexItemRepository,
            $this->queue,
            $this->queueItemRepository,
            $this->extensionConfiguration,
            $this->metadataRepository
        );
    }

    private static function item(int $itemUid, int $root = 1, string $configuration = 'sys_file_metadata'): array
    {
        return ['item_uid' => $itemUid, 'root' => $root, 'indexing_configuration' => $configuration];
    }

    /**
     * Records of other tables are left to EXT:solr.
     */
    #[Test]
    public function otherTablesAreIgnored(): void
    {
        $event = new RecordUpdatedEvent(5, 'pages');
        $this->queue->expects(self::never())->method('saveItemForRootpage');
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()($event);

        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * With record monitoring disabled in EXT:solr ("no processing"), the listener does nothing.
     */
    #[Test]
    public function nothingHappensIfMonitoringIsDisabled(): void
    {
        $this->extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $this->extensionConfiguration->method('getMonitoringType')->willReturn(2);
        $event = new RecordUpdatedEvent(51, 'sys_file_metadata');
        $this->queue->expects(self::never())->method('saveItemForRootpage');
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()($event);

        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * Saving metadata must not reach EXT:solr (which would remove the file) and re-queues the file instead.
     */
    #[Test]
    public function updatedMetadataStopsProcessingAndRequeuesFile(): void
    {
        $this->indexItemRepository->method('findByMetadataUid')->willReturnMap([[51, [self::item(51)]]]);
        $event = new RecordUpdatedEvent(51, 'sys_file_metadata');
        $this->queue->expects(self::once())->method('saveItemForRootpage')
            ->with('sys_file_metadata', 51, 1, 'sys_file_metadata', []);
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()($event);

        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * A file in several root pages is re-queued for each of them, every queue item only once.
     */
    #[Test]
    public function fileIsRequeuedOncePerRootPage(): void
    {
        $this->indexItemRepository->method('findByMetadataUid')->willReturnMap([
            [51, [self::item(51, 1), self::item(51, 1), self::item(51, 7)]],
        ]);
        $this->queue->expects(self::exactly(2))->method('saveItemForRootpage');
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()(new RecordUpdatedEvent(51, 'sys_file_metadata'));
    }

    /**
     * A new translation is not yet known to the file indexer items, so the default language record is re-queued.
     */
    #[Test]
    public function insertedTranslationRequeuesFileOfDefaultLanguageRecord(): void
    {
        $this->metadataRepository->method('findLanguageParentUid')->willReturnMap([[60, 52]]);
        $this->indexItemRepository->method('findByMetadataUid')->willReturnMap([
            [60, []],
            [52, [self::item(52)]],
        ]);
        $event = new RecordInsertedEvent(60, 'sys_file_metadata');
        $this->queue->expects(self::once())->method('saveItemForRootpage')
            ->with('sys_file_metadata', 52, 1, 'sys_file_metadata', []);
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()($event);

        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * Deleting a translation keeps the file in the index and forces re-indexing, as the default record did not change.
     */
    #[Test]
    public function deletedTranslationRequeuesFileAndForcesReindexing(): void
    {
        $this->metadataRepository->method('findLanguageParentUid')->willReturnMap([[60, 52]]);
        $this->indexItemRepository->method('findByMetadataUid')->willReturnMap([
            [60, [self::item(52)]],
            [52, [self::item(52)]],
        ]);
        $event = new RecordDeletedEvent(60, 'sys_file_metadata');
        $this->queue->expects(self::once())->method('saveItemForRootpage');
        $this->queueItemRepository->expects(self::once())->method('updateItemsChangedTime')
            ->with(self::greaterThan(0), [], [], ['sys_file_metadata'], [52]);

        $this->getSubject()($event);

        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * Deleting the default language metadata record is left to EXT:solr (the file is removed anyway).
     */
    #[Test]
    public function deletedDefaultLanguageRecordIsLeftToSolr(): void
    {
        $this->metadataRepository->method('findLanguageParentUid')->willReturn(0);
        $event = new RecordDeletedEvent(52, 'sys_file_metadata');
        $this->queue->expects(self::never())->method('saveItemForRootpage');
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');

        $this->getSubject()($event);

        self::assertFalse($event->isPropagationStopped());
    }
}
