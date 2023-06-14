<?php
if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \HMMH\SolrFileIndexer\Hook\GarbageCollector::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\HMMH\SolrFileIndexer\Task\DeleteByTypeTask::class] = [
    'extension' => 'solr_file_indexer',
    'title' => 'Remove type:sys_file_metadata from solr index',
    'description' => '',
    'additionalFields' => \HMMH\SolrFileIndexer\Task\DeleteByTypeTaskAdditionalFieldProvider::class
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_dashboard.view.templateRootPaths.20 = EXT:solr_file_indexer/Resources/Private/Templates'
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService::class] = [
   'className' => HMMH\SolrFileIndexer\Service\WriteServiceAdapter::class
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor::class] = [
   'className' => HMMH\SolrFileIndexer\IndexQueue\RecordMonitorAdapter::class
];
