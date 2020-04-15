<?php
namespace HMMH\SolrFileIndexer\Tests\Unit\Service;

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

use ApacheSolrForTypo3\Tika\Service\Tika\ServiceInterface;
use ApacheSolrForTypo3\Tika\Service\Tika\SolrCellService;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Service\ServiceFactory;
use HMMH\SolrFileIndexer\Service\Tika\SolrService;
use Nimut\TestingFramework\MockObject\AccessibleMockObjectInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;

/**
 * Class ServiceFactoryTest
 *
 * @package HMMH\SolrFileIndexer\Tests\Unit\Service
 */
class ServiceFactoryTest extends UnitTestCase
{
    /**
     * @var ServiceFactory|AccessibleMockObjectInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $instance;

    public function setUp()
    {
        parent::setUp();

        $this->instance = $this->getAccessibleMock(
            ServiceFactory::class,
            ['getExtensionConfig', 'getSolrService', 'isTikaActive', 'getTikaExtensionService']
        );
    }

    /**
     * @test
     * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
     */
    public function getTikaWithoutExtTikaReturnSolrService()
    {
        $extConfig = [
          'useTika' => '0',
          'solrSite' => '1',
        ];

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr_file_indexer'] = $extConfig;

        $solrService = $this->getAccessibleMock(SolrService::class, ['dummy'], [], '', false);

        $this->instance->expects($this->once())->method('getExtensionConfig')->willReturn(new ExtensionConfig());
        $this->instance->expects($this->once())->method('getSolrService')->willReturn($solrService);

        $result = $this->instance->_callRef('getTikaService');
        $this->assertInstanceOf(SolrService::class, $result);
    }

    /**
     * @test
     * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
     */
    public function getTikaWithExtTikaNeverCallsGetSolrServiceAndThrowsExceptionIfTikaNotExistsOrReturnTikaServer()
    {
        $extConfig = [
            'useTika' => '1',
            'solrSite' => '1',
        ];

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr_file_indexer'] = $extConfig;

        $tikaConfig = [
            'extractor' => 'solr'
        ];

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tika'] = serialize($tikaConfig);

        $this->instance->expects($this->once())->method('getExtensionConfig')->willReturn(new ExtensionConfig());
        $this->instance->expects($this->never())->method('getSolrService');
        $this->instance->expects($this->once())->method('isTikaActive')->willReturn(false);

        $this->expectException(UnknownPackageException::class);

        $this->instance->_callRef('getTikaService');
    }
}
