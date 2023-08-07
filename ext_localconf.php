<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use HMMH\SolrFileIndexer\Hook\DataHandlerHook;
use HMMH\SolrFileIndexer\Service\WriteServiceAdapter;
use HMMH\SolrFileIndexer\Task\DeleteByTypeTask;
use HMMH\SolrFileIndexer\Task\DeleteByTypeTaskAdditionalFieldProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][DeleteByTypeTask::class] = [
    'extension' => 'solr_file_indexer',
    'title' => 'Remove type:sys_file_metadata from solr index',
    'description' => '',
    'additionalFields' => DeleteByTypeTaskAdditionalFieldProvider::class
];

ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_dashboard.view.templateRootPaths.20 = EXT:solr_file_indexer/Resources/Private/Templates'
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SolrWriteService::class] = [
   'className' => WriteServiceAdapter::class
];
