<?php
defined('TYPO3_MODE') or die();

//Add Fields to the page editing.
$tmpColum = [
    'enable_indexing' => [
        'label'  => 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:enable_indexing',
        'config' => [
            'type'    => 'select',
            'renderType' => 'selectCheckBox',
            'default' => '',
            'foreign_table' => 'pages',
            'foreign_table_where' => 'AND is_siteroot=1 ORDER BY pages.title'
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $tmpColum);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.tabs.search, enable_indexing',
    ''
);
