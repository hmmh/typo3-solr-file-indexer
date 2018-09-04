<?php
namespace HMMH\SolrFileIndexer\Configuration;

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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class ExtensionConfig
 *
 * @package HMMH\SolrFileIndexer\Configuration
 */
class ExtensionConfig implements SingletonInterface
{
    /**
     * @var array
     */
    protected $extConfig = [];

    /**
     * ExtensionConfig constructor.
     */
    public function __construct()
    {
        $this->extConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['solr_file_indexer']);
    }

    /**
     * @return bool
     */
    public function useTika(): bool
    {
        return (bool)$this->extConfig['useTika'];
    }

    /**
     * @return int
     */
    public function getPageId(): int
    {
        return (int)$this->extConfig['solrSite'];
    }

    /**
     * @return bool
     */
    public function ignoreLocalization(): bool
    {
        return (bool)$this->extConfig['ignoreLocalization'];
    }
}
