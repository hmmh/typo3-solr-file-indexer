<?php
namespace HMMH\SolrFileIndexer\Tests\Unit\Command;

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

use HMMH\SolrFileIndexer\Command\SolrCommandController;
use Nimut\TestingFramework\MockObject\AccessibleMockObjectInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class SolrCommandControllerTest
 *
 * @package HMMH\SolrFileIndexer\Tests\Unit\Command
 */
class SolrCommandControllerTest extends UnitTestCase
{

    /**
     * @var SolrCommandController|AccessibleMockObjectInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $instance;

    public function setUp()
    {
        parent::setUp();

        $this->instance = $this->getAccessibleMock(SolrCommandController::class, ['deleteByType', 'reindexByType', 'setSite']);
    }

    /**
     * @test
     */
    public function commandWithReindexingCallReindex()
    {
        $this->instance->expects($this->once())
            ->method('reindexByType');

        $this->instance->deleteByTypeCommand(1, 'sys_file_metadata', true);
    }

    /**
     * @test
     */
    public function commandWithoutReindexingCallNoReindex()
    {
        $this->instance->expects($this->never())
            ->method('reindexByType');

        $this->instance->deleteByTypeCommand(1, 'sys_file_metadata', false);
    }
}
