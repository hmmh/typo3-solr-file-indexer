<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

use HMMH\SolrFileIndexer\Updates\MoveMetadataToFileCollections;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['solrFileIndexerFileCollections'] = MoveMetadataToFileCollections::class;
