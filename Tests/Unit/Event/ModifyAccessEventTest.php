<?php

/**
 * This file is part of the package hmmh/solr-file-indexer.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HMMH\SolrFileIndexer\Tests\Unit\Event;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use HMMH\SolrFileIndexer\Event\ModifyAccessEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * This test verifies the ModifyAccessEvent correctly stores and exposes
 * the access rootline, index queue item, and file reference so that
 * event listeners can inspect and modify the Solr access field.
 */
#[CoversClass(ModifyAccessEvent::class)]
final class ModifyAccessEventTest extends TestCase
{
    /**
     * This test verifies the constructor sets all properties and getters return the expected values.
     */
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        $item = self::createStub(Item::class);
        $file = self::createStub(FileInterface::class);

        $event = new ModifyAccessEvent('r:0', $item, $file);

        self::assertSame('r:0', $event->getAccessRootline());
        self::assertSame($item, $event->getItem());
        self::assertSame($file, $event->getFile());
    }

    /**
     * This test verifies that setAccessRootline overwrites the initial value
     * and subsequent calls to getAccessRootline return the updated value.
     */
    #[Test]
    public function setAccessRootlineOverwritesValue(): void
    {
        $item = self::createStub(Item::class);
        $file = self::createStub(FileInterface::class);

        $event = new ModifyAccessEvent('r:0', $item, $file);
        $event->setAccessRootline('r:3,7');

        self::assertSame('r:3,7', $event->getAccessRootline());
    }

    /**
     * This test verifies that when no listener modifies the event,
     * the original access rootline is preserved unchanged.
     */
    #[Test]
    public function accessRootlineRemainsUnchangedWithoutModification(): void
    {
        $item = self::createStub(Item::class);
        $file = self::createStub(FileInterface::class);

        $event = new ModifyAccessEvent('r:5', $item, $file);

        self::assertSame('r:5', $event->getAccessRootline());
    }
}
