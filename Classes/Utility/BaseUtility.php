<?php

namespace HMMH\SolrFileIndexer\Utility;

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
