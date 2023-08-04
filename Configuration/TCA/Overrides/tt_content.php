<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * TypoScript
 */
ExtensionManagementUtility::addStaticFile('solr_file_indexer', 'Configuration/TypoScript', 'Solr file indexing');
