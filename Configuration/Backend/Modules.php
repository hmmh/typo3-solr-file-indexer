<?php

use HMMH\SolrFileIndexer\Controller\Backend\FileAdministrationController;

return [
    'solr_file_indexer_fileadministration' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        // The items are not related to a page, so the page tree of the Solr main module is not shown
        'inheritNavigationComponentFromMainModule' => false,
        'path' => '/module/searchbackend/solr-file-indexer-file-administration',
        'iconIdentifier' => 'extensions-solr-file-indexer-module-file-admin',
        'labels' => 'solr_file_indexer.mod_fileadmin',
        'extensionName' => 'SolrFileIndexer',
        'controllerActions' => [
            FileAdministrationController::class => [
                'index', 'clear'
            ],
        ],
    ],
];
