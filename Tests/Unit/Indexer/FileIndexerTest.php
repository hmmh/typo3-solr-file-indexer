<?php
namespace HMMH\SolrFileIndexer\Tests\Unit\Indexer;

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

use Apache_Solr_Document;
use HMMH\SolrFileIndexer\Indexer\FileIndexer;
use HMMH\SolrFileIndexer\Interfaces\DocumentUrlInterface;
use Nimut\TestingFramework\MockObject\AccessibleMockObjectInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Class FileIndexerTest
 *
 * @package HMMH\SolrFileIndexer\Tests\Unit\Indexer
 */
class FileIndexerTest extends UnitTestCase
{

    /**
     * @var FileIndexer|AccessibleMockObjectInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $instance;

    public function setUp()
    {
        parent::setUp();

        $this->instance = $this->getAccessibleMock(
            FileIndexer::class,
            ['setLogging', 'getSolrConnectionsByItem', 'getIndexableFile', 'indexItem', 'fetchFile', 'getSignalSlotDispatcher'],
            [],
            '',
            false
        );

        $extConfig = [
          'useTika' => '0',
          'solrSite' => '1',
        ];

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr_file_indexer'] = $extConfig;
    }

    public function tearDown()
    {
        parent::tearDown();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addDocumentUrl'] = null;
    }

    /**
     * @test
     */
    public function indexItemReturnTrue()
    {
        $this->instance->expects($this->once())
            ->method('getSolrConnectionsByItem')->willReturn([]);

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $result = $this->instance->index($item);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function indexItemWithTwoConnectionsCallGetIndexableFileTwoTimes()
    {
        $this->instance->expects($this->once())
            ->method('getSolrConnectionsByItem')->willReturn([0 => [], 1 => []]);

        $this->instance->expects($this->exactly(2))->method('getIndexableFile');

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $result = $this->instance->index($item);
    }

    /**
     * @test
     */
    public function indexItemWithoutIndexableFileCallNoIndexItem()
    {
        $this->instance->expects($this->once())
            ->method('getSolrConnectionsByItem')->willReturn([0 => []]);

        $this->instance->expects($this->once())->method('getIndexableFile')->willReturn(null);
        $this->instance->expects($this->never())->method('indexItem');

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $result = $this->instance->index($item);
    }

    /**
     * @test
     */
    public function indexItemWithIndexableFileCallIndexItem()
    {
        $this->instance->expects($this->once())
            ->method('getSolrConnectionsByItem')->willReturn([0 => []]);

        $this->instance->expects($this->once())->method('getIndexableFile')->willReturn([]);
        $this->instance->expects($this->once())->method('indexItem');

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $result = $this->instance->index($item);
    }

    /**
     * @test
     */
    public function addDocumentUrlWithCorrectHookCallNeverFetchFile()
    {
        $addDocumentUrl = $this->getMockClass(DocumentUrlInterface::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addDocumentUrl'][] = $addDocumentUrl;

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $document = new \ApacheSolrForTypo3\Solr\System\Solr\Document\Document();

        $this->instance->expects($this->never())->method('fetchFile');

        $this->instance->_callRef('addDocumentUrl', $item, $document);
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function addDocumentUrlWithIncorrectHookThrowsException()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addDocumentUrl'][] = FileIndexerTest::class;

        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $document = new \ApacheSolrForTypo3\Solr\System\Solr\Document\Document();

        $this->instance->_callRef('addDocumentUrl', $item, $document);

        $this->expectExceptionCode(1345807460);
    }

    /**
     * @test
     */
    public function addDocumentUrlWithoutHookCallFetchFile()
    {
        $item = $this->getMockBuilder(Item::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $document = new \ApacheSolrForTypo3\Solr\System\Solr\Document\Document();

        $this->instance->expects($this->once())->method('fetchFile');

        $this->instance->_callRef('addDocumentUrl', $item, $document);
    }

    /**
     * @test
     */
    public function cleanupContentReturnTrimString()
    {
        $dispatcher = $this->getMockBuilder(Dispatcher::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $this->instance->expects($this->once())->method('getSignalSlotDispatcher')->willReturn($dispatcher);

        $content = ' abcde ';
        $result = $this->instance->_callRef('cleanupContent', $content);

        $this->assertSame(trim($content), $result);
    }
}
