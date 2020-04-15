<?php
namespace HMMH\SolrFileIndexer\Tests\Unit\Configuration;

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

use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use Nimut\TestingFramework\TestCase\UnitTestCase;

/**
 * Class ExtensionConfigTest
 *
 * @package HMMH\SolrFileIndexer\Tests\Unit\Configuration
 */
class ExtensionConfigTest extends UnitTestCase
{

    /**
     * @var ExtensionConfig
     */
    protected $instance;

    public function setUp()
    {
        parent::setUp();

        $extConfig = [
          'useTika' => '0',
          'solrSite' => '1',
        ];

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr_file_indexer'] = $extConfig;

        $this->instance = new ExtensionConfig();
    }

    /**
     * @test
     */
    public function getUseTikaReturnsBoolean()
    {
        $this->assertFalse($this->instance->useTika());
    }

    /**
     * @test
     */
    public function getPageIdReturnsInteger()
    {
        $this->assertSame(1, $this->instance->getPageId());
    }
}
