<?php

/**
 * This file is part of the package hmmh/solr-file-indexer.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HMMH\SolrFileIndexer\Tests\Unit\Indexer;

use ReflectionMethod;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use HMMH\SolrFileIndexer\Event\ModifyAccessEvent;
use HMMH\SolrFileIndexer\Indexer\FileIndexer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * This test verifies that FileIndexer dispatches a ModifyAccessEvent during
 * access rootline generation, allowing external extensions to set the Solr
 * access field for file documents based on frontend user group permissions.
 */
#[CoversClass(FileIndexer::class)]
final class FileIndexerTest extends UnitTestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;

    private FileIndexer&MockObject $fileIndexer;

    private FileInterface&MockObject $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = self::createStub(FileInterface::class);

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->fileIndexer = $this->getMockBuilder(FileIndexer::class)
            ->setConstructorArgs([
                [],
                self::createStub(PagesRepository::class),
                self::createStub(Builder::class),
                self::createStub(ConnectionManager::class),
                self::createStub(FrontendEnvironment::class),
                self::createStub(SolrLogManager::class),
                $this->eventDispatcher,
            ])
            ->onlyMethods(['fetchFile'])
            ->getMock();

        $this->fileIndexer
            ->method('fetchFile')
            ->willReturn($this->file);
    }

    /**
     * This test verifies that getAccessRootline dispatches a ModifyAccessEvent
     * containing the parent's access rootline value, the index queue item,
     * and the resolved file reference.
     */
    #[Test]
    public function getAccessRootlineDispatchesModifyAccessEvent(): void
    {
        $item = self::createStub(Item::class);
        $item->method('getType')->willReturn('sys_file_metadata');
        $item->method('getRecord')->willReturn(['uid' => 1, 'pid' => 0]);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn ($event): bool => $event instanceof ModifyAccessEvent
                    && $event->getAccessRootline() === 'r:0'
                    && $event->getItem() === $item
                    && $event->getFile() === $this->file
            ))
            ->willReturnArgument(0);

        $reflection = new ReflectionMethod($this->fileIndexer, 'getAccessRootline');
        $result = $reflection->invoke($this->fileIndexer, $item);

        self::assertSame('r:0', $result);
    }

    /**
     * This test verifies that when an event listener modifies the access rootline
     * via setAccessRootline(), the modified value is returned by getAccessRootline().
     */
    #[Test]
    public function getAccessRootlineReturnsModifiedValueFromEventListener(): void
    {
        $item = self::createStub(Item::class);
        $item->method('getType')->willReturn('sys_file_metadata');
        $item->method('getRecord')->willReturn(['uid' => 1, 'pid' => 0]);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (ModifyAccessEvent $event): ModifyAccessEvent {
                $event->setAccessRootline('r:3,7');
                return $event;
            });

        $reflection = new ReflectionMethod($this->fileIndexer, 'getAccessRootline');
        $result = $reflection->invoke($this->fileIndexer, $item);

        self::assertSame('r:3,7', $result);
    }

    /**
     * This test verifies that when fetchFile returns null (file not found),
     * no ModifyAccessEvent is dispatched and the parent's access rootline
     * value is returned unchanged.
     */
    #[Test]
    public function getAccessRootlineSkipsEventWhenFileIsNull(): void
    {
        $item = self::createStub(Item::class);
        $item->method('getType')->willReturn('sys_file_metadata');
        $item->method('getRecord')->willReturn(['uid' => 1, 'pid' => 0]);

        $indexer = $this->getMockBuilder(FileIndexer::class)
            ->setConstructorArgs([
                [],
                self::createStub(PagesRepository::class),
                self::createStub(Builder::class),
                self::createStub(ConnectionManager::class),
                self::createStub(FrontendEnvironment::class),
                self::createStub(SolrLogManager::class),
                $this->eventDispatcher,
            ])
            ->onlyMethods(['fetchFile'])
            ->getMock();

        $indexer
            ->method('fetchFile')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $reflection = new ReflectionMethod($indexer, 'getAccessRootline');
        $result = $reflection->invoke($indexer, $item);

        self::assertSame('r:0', $result);
    }
}
