<?php
namespace HMMH\SolrFileIndexer\Tests\Unit\IndexQueue;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use ApacheSolrForTypo3\Solr\Site;
use HMMH\SolrFileIndexer\IndexQueue\FileInitializer;
use HMMH\SolrFileIndexer\IndexQueue\Queue;
use Nimut\TestingFramework\MockObject\AccessibleMockObjectInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophecy\ProphecySubjectInterface;

/**
 * Class FileInitializerTest
 *
 * @package HMMH\SolrFileIndexer\Tests\Unit\IndexQueue
 */
class FileInitializerTest extends UnitTestCase
{

    /**
     * @var FileInitializer|AccessibleMockObjectInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $instance;

    /**
     * @var ObjectProphecy|Site
     */
    protected $siteProphecy;

    /**
     * @var ProphecySubjectInterface|Site
     */
    protected $site;

    /**
     * @var ObjectProphecy|Queue
     */
    protected $queueProphecy;

    /**
     * @var ProphecySubjectInterface|Queue
     */
    protected $queue;

    public function setUp()
    {
        parent::setUp();

        $this->instance = $this->getAccessibleMock(
            FileInitializer::class,
            ['getMetadataForSiteroot', 'getAllEnabledMetadata']
        );

        $this->siteProphecy = $this->prophesize(Site::class);
        $this->site = $this->siteProphecy->reveal();
        $this->inject($this->instance, 'site', $this->site);

        $this->queueProphecy = $this->prophesize(Queue::class);
        $this->queue = $this->queueProphecy->reveal();
        $this->inject($this->instance, 'queue', $this->queue);
    }

    /**
     * @test
     */
    public function initializeWithoutIndexRowsReturnFalse()
    {
        $result = $this->instance->initialize();
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function initializeWithIndexRowsAndWithoutErrorsReturnTrue()
    {
        $indexRows[] = [
            'root' => 1,
            'item_type' => 'sys_file_metadata',
            'item_uid' => 1,
            'indexing_configuration' => 'sys_file_metadata',
            'indexing_priority' => 0,
            'changed' => time()
        ];

        $this->instance->expects($this->once())->method('getMetadataForSiteroot')->willReturn($indexRows);
        $this->queueProphecy->addMultipleItemsToQueue($indexRows)->willReturn(true);
        $result = $this->instance->initialize();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function initializeWithIndexRowsAndWithErrorsReturnFalse()
    {
        $indexRows[] = [
            'root' => 1,
            'item_type' => 'sys_file_metadata',
            'item_uid' => 1,
            'indexing_configuration' => 'sys_file_metadata',
            'indexing_priority' => 0,
            'changed' => time()
        ];

        $this->instance->expects($this->once())->method('getMetadataForSiteroot')->willReturn($indexRows);
        $this->queueProphecy->addMultipleItemsToQueue($indexRows)->willReturn(false);
        $result = $this->instance->initialize();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function getMetadataForSiterootWithEnabledMetadataForOneSiterootReturnIndexRows()
    {
        $this->instance = $this->getAccessibleMock(
            FileInitializer::class,
            ['getAllEnabledMetadata']
        );
        $this->inject($this->instance, 'site', $this->site);

        $metadata[] = [
            'enable_indexing' => '1',
            'uid' => 1,
            'changed' => time()
        ];

        $this->instance->expects($this->any())->method('getAllEnabledMetadata')->willReturn($metadata);
        $this->siteProphecy->getRootPageId()->willReturn(1);

        $result = $this->instance->_callRef('getMetadataForSiteroot');
        $this->assertCount(1, $result);
    }

    /**
     * @test
     */
    public function getMetadataForSiterootWithEnabledMetadataForOtherSiterootReturnEmptyArray()
    {
        $this->instance = $this->getAccessibleMock(
            FileInitializer::class,
            ['getAllEnabledMetadata']
        );
        $this->inject($this->instance, 'site', $this->site);

        $metadata[] = [
            'enable_indexing' => '2',
            'uid' => 1,
            'changed' => time()
        ];

        $this->instance->expects($this->any())->method('getAllEnabledMetadata')->willReturn($metadata);
        $this->siteProphecy->getRootPageId()->willReturn(1);

        $result = $this->instance->_callRef('getMetadataForSiteroot');
        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getMetadataForSiterootWithEnabledMetadataForMoreSiterootsReturnIndexRows()
    {
        $this->instance = $this->getAccessibleMock(
            FileInitializer::class,
            ['getAllEnabledMetadata']
        );
        $this->inject($this->instance, 'site', $this->site);

        $metadata[] = [
            'enable_indexing' => '1,2',
            'uid' => 1,
            'changed' => time()
        ];

        $this->instance->expects($this->any())->method('getAllEnabledMetadata')->willReturn($metadata);
        $this->siteProphecy->getRootPageId()->willReturn(1);

        $result = $this->instance->_callRef('getMetadataForSiteroot');
        $this->assertCount(1, $result);
    }

    /**
     * @test
     */
    public function getMetadataForSiterootWithMultipleEnabledMetadataForDifferentSiterootsReturnIndexRowsForOneSiteroot()
    {
        $this->instance = $this->getAccessibleMock(
            FileInitializer::class,
            ['getAllEnabledMetadata']
        );
        $this->inject($this->instance, 'site', $this->site);

        $metadata = [
            [
                'enable_indexing' => '1',
                'uid' => 1,
                'changed' => time()
            ],
            [
                'enable_indexing' => '2',
                'uid' => 2,
                'changed' => time()
            ]
        ];

        $this->instance->expects($this->any())->method('getAllEnabledMetadata')->willReturn($metadata);
        $this->siteProphecy->getRootPageId()->willReturn(1);

        $result = $this->instance->_callRef('getMetadataForSiteroot');
        $this->assertCount(1, $result);
    }
}
