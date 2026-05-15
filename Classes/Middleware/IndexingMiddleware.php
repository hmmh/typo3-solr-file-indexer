<?php

namespace HMMH\SolrFileIndexer\Middleware;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Middleware\SolrIndexingMiddleware;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

readonly class IndexingMiddleware extends SolrIndexingMiddleware
{
    protected function getFullItemRecord(Item $item, int $language): ?array
    {
        if ($item->getType() !== 'sys_file_metadata') {
            return parent::getFullItemRecord($item, $language);
        }

        $itemRecord = $item->getRecord();
        if (!is_array($itemRecord)) {
            return null;
        }

        /** @var IndexItemRepository $indexItemRepository */
        $indexItemRepository = GeneralUtility::makeInstance(IndexItemRepository::class);
        $indexItem = $indexItemRepository->findIndexableItem(
            $itemRecord['uid'],
            $item->getRootPageUid(),
            $language,
            $item->getType(),
            $item->getIndexingConfigurationName()
        );

        if (empty($indexItem)) {
            return null;
        }

        if ($language > 0 && $indexItem['item_uid'] !== $indexItem['localized_uid']) {
            $translatedRecord = BackendUtility::getRecord(MetadataRepository::FILE_TABLE, $indexItem['localized_uid']);
            if (!empty($translatedRecord)) {
                return $translatedRecord;
            }
        }

        return $itemRecord;
    }

}
