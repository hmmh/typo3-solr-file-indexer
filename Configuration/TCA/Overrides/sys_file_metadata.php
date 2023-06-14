<?php
defined('TYPO3') or die();

//Add Fields to the page editing.
$tmpColum = [
    'enable_indexing' => [
        'label'  => 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:enable_indexing',
        'config' => [
            'type'    => 'select',
            'renderType' => 'selectCheckBox',
            'default' => '',
            'foreign_table' => 'pages',
            'foreign_table_where' => 'AND is_siteroot=1 AND sys_language_uid=0 ORDER BY pages.uid'
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $tmpColum);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.tabs.search, enable_indexing',
    ''
);
