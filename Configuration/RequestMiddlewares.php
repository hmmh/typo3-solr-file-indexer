<?php

return [
    'frontend' => [
        /*'apache-solr-for-typo3/indexing' => [
            'disabled' => true,
        ],
        'apache-solr-for-typo3-solr-file/indexing' => [
            'target' => \HMMH\SolrFileIndexer\Middleware\IndexingMiddleware::class,
            'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ],*/
        'apache-solr-for-typo3/indexing' => [
            'target' => \HMMH\SolrFileIndexer\Middleware\IndexingMiddleware::class,
            'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ]
    ]
];
