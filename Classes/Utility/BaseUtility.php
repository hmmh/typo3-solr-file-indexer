<?php

namespace HMMH\SolrFileIndexer\Utility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2023 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

class BaseUtility
{
    const METADATA_TABLE = 'sys_file_metadata';

    public static function getMetadataLanguageField()
    {
        return $GLOBALS['TCA'][self::METADATA_TABLE]['ctrl']['languageField'];
    }

    public static function getMetadataLanguageParentField()
    {
        return $GLOBALS['TCA'][self::METADATA_TABLE]['ctrl']['transOrigPointerField'];
    }

    public static function getMetadataTstampField()
    {
        return $GLOBALS['TCA'][self::METADATA_TABLE]['ctrl']['tstamp'];
    }
}
