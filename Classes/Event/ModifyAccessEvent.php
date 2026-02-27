<?php

namespace HMMH\SolrFileIndexer\Event;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2025 Rico Sonntag <rico.sonntag@netresearch.de>
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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Event dispatched during file indexing to allow modification of the Solr access rootline.
 *
 * This event enables extensions (e.g. fal_securedownload, fal_protect) to set the
 * correct access restrictions on file documents in the Solr index based on
 * frontend user group permissions configured on files or folders.
 */
final class ModifyAccessEvent
{
    public function __construct(
        private string $accessRootline,
        private readonly Item $item,
        private readonly FileInterface $file,
    ) {
    }

    public function getAccessRootline(): string
    {
        return $this->accessRootline;
    }

    public function setAccessRootline(string $accessRootline): void
    {
        $this->accessRootline = $accessRootline;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getFile(): FileInterface
    {
        return $this->file;
    }
}
