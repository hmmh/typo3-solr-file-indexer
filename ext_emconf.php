<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "solr_file_indexer"
 *
 * Auto generated by Extension Builder 2016-08-10
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['solr_file_indexer'] = [
    'title'            => 'Solr file indexing',
    'description'      => 'This extension gives you the capability to index individual documents using Solr.',
    'category'         => 'plugin',
    'author'           => 'hmmh multimediahaus AG',
    'author_email'     => 'typo3@hmmh.de',
    'state'            => 'stable',
    'internal'         => '',
    'uploadfolder'     => '0',
    'createDirs'       => '',
    'clearCacheOnLoad' => 0,
    'version'          => '3.0.0',
    'constraints'      => [
        'depends'   => [
            'typo3' => '12.4.0-12.4.99',
            'solr' => '>=12.0',
            'php' => '>=8.1'
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
