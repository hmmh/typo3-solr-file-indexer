<?php
defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$tmpColum = [
    'use_for_solr' => [
        'label'  => 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:use_for_solr',
        'config' => [
            'type'    => 'select',
            'renderType' => 'selectCheckBox',
            'default' => '',
            'foreign_table' => 'pages',
            'foreign_table_where' => 'AND is_siteroot=1 AND sys_language_uid=0 ORDER BY pages.uid'
        ]
    ]
];

ExtensionManagementUtility::addTCAcolumns('sys_file_collection', $tmpColum);
ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_collection',
    '--div--;LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:sys_file_collection.tabs.search, use_for_solr',
    ''
);
