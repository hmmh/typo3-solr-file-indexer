<?php
namespace HMMH\SolrFileIndexer\Hook;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use HMMH\SolrFileIndexer\Service\IndexHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GarbageCollector
 *
 * @package HMMH\SolrFileIndexer\Hook
 */
class GarbageCollector
{
    const FILE_TABLE = 'sys_file_metadata';

    /**
     * Hooks into TCE Main and watches all record updates. If a change is
     * detected that would remove the record from the website, we try to find
     * related documents and remove them from the index.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data, not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array $fields,
        DataHandler $tceMain
    ): void {
        if ($table === self::FILE_TABLE && $status === 'update') {
            $indexHandler = GeneralUtility::makeInstance(IndexHandler::class);
            $indexHandler->updateMetadata($uid);
        }
    }
}
