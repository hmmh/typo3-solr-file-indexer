<?php

namespace HMMH\SolrFileIndexer\Service;

use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use Solarium\QueryType\Extract\Query;

/**
 * Class WriteServiceAdapter
 *
 * @package HMMH\SolrFileIndexer\Service
 */
class WriteServiceAdapter extends SolrWriteService
{
    /**
     * @param Query $query
     * @return array
     */
    public function extractByQuery(Query $query): array
    {
        try {
            $response = $this->createAndExecuteRequest($query);
            $fileName = basename($query->getFile());
            $metaKey = $fileName . '_metadata';
            return [
                $response->{$fileName} ?? $response->file,
                (array)($response->{$metaKey} ?? $response->file_metadata)
            ];
        } catch (\Exception $e) {
            $param = $query->getRequestBuilder()->build($query)->getParams();
            $this->logger->log(
                SolrLogManager::ERROR,
                'Extracting text and meta data through Solr Cell over HTTP POST',
                [
                    'query' => (array)$query,
                    'parameters' => $param,
                    'file' => $query->getFile(),
                    'query url' => self::EXTRACT_SERVLET,
                    'exception' => $e->getMessage()
                ]
            );
        }

        return [];
    }
}
