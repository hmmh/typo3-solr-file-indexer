<?php

use HMMH\SolrFileIndexer\Controller\Backend\FileAdministrationController;

return [
    'solr_file_indexer_fileadministration' => [
        'parent' => 'searchbackend',
        'access' => 'user,group',
        'path' => '/module/searchbackend/solr-file-indexer-file-administration',
        'iconIdentifier' => 'extensions-solr-file-indexer-module-file-admin',
        'labels' => 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_mod_fileadmin.xlf',
        'extensionName' => 'SolrFileIndexer',
        'controllerActions' => [
            FileAdministrationController::class => [
                'index', 'clear'
            ],
        ],
    ],
];
