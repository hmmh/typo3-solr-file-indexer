<?php

namespace HMMH\SolrFileIndexer\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;

/**
 * Class RecordMonitorAdapter
 *
 * @package HMMH\SolrFileIndexer\IndexQueue
 */
class RecordMonitorAdapter extends RecordMonitor
{
    /**
     * Check if the provided table is explicitly configured for monitoring
     *
     * @param string $table
     * @return bool
     */
    protected function skipMonitoringOfTable($table): bool
    {
        if ($table === 'sys_file_metadata') {
            return true;
        }

        return parent::skipMonitoringOfTable($table);
    }
}
