<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \HMMH\SolrFileIndexer\Hook\GarbageCollector::class;

$signalSlotDispatcher = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
$signalSlotDispatcher->connect(
    \TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class,
    'recordDeleted',
    \HMMH\SolrFileIndexer\Hook\GarbageCollector::class,
    'deleteFile'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\HMMH\SolrFileIndexer\Task\DeleteByTypeTask::class] = [
    'extension' => $_EXTKEY,
    'title' => 'Remove type:sys_file_metadata from solr index',
    'description' => '',
    'additionalFields' => \HMMH\SolrFileIndexer\Task\DeleteByTypeTaskAdditionalFieldProvider::class
];
