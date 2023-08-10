<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

use HMMH\SolrFileIndexer\Task\DeleteByTypeTask;
use HMMH\SolrFileIndexer\Task\DeleteByTypeTaskAdditionalFieldProvider;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][DeleteByTypeTask::class] = [
    'extension' => 'solr_file_indexer',
    'title' => 'Remove type:sys_file_metadata from solr index',
    'description' => '',
    'additionalFields' => DeleteByTypeTaskAdditionalFieldProvider::class
];
