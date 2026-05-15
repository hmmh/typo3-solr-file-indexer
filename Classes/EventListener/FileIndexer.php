<?php

namespace HMMH\SolrFileIndexer\EventListener;

use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Event\AddDocumentUrlEvent;
use HMMH\SolrFileIndexer\Event\ModifyAccessEvent;
use HMMH\SolrFileIndexer\Event\ModifyContentEvent;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\Service\ServiceFactory;
use HMMH\SolrFileIndexer\Service\SolrService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FileIndexer
{
    protected array $fileCache = [];

    protected ?SolrConnection $currentlyUsedSolrConnection;

    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {}

    #[AsEventListener(
        identifier: 'solr_file_indexer.FileIndexer',
    )]
    public function __invoke(BeforeDocumentsAreIndexedEvent $event): void
    {
        $item = $event->getIndexQueueItem();
        if ($item->getType() !== 'sys_file_metadata') {
            return;
        }

        $document = $event->getDocument();
        $documents = $event->getDocuments();
        $siteLang = $event->getSiteLanguage();
        $site = $item->getSite();
        $languageUid = $siteLang->getLanguageId();

        $connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);
        $solrConnections = $connectionAdapter->getConnectionsBySite($site);

        if (empty($GLOBALS['BE_USER'])) {
            $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        }

        if (isset($solrConnections[$languageUid]) && $solrConnections[$languageUid] instanceof SolrConnection) {
            $this->currentlyUsedSolrConnection = $solrConnections[$languageUid];
            $indexableFile = $this->getIndexableFile($item, $languageUid);
            if ($indexableFile !== null) {
                $content = $this->getFileContent($indexableFile);
                $modifyContentEvent = new ModifyContentEvent($content);
                $modifyContentEvent = $this->eventDispatcher->dispatch($modifyContentEvent);
                $content = $modifyContentEvent->getContent();

                $document->setField('content', $content);
                $publicUrl = $indexableFile->getPublicUrl();
                if ($this->isLocalResource($indexableFile)) {
                    if (str_starts_with($publicUrl, '/')) {
                        $publicUrl = ltrim($publicUrl, '/');
                    }
                    $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfig::class);
                    $publicUrl = $extensionConfiguration->getLocalPrefix() . $publicUrl;
                }
                $addDocumentUrlEvent = new AddDocumentUrlEvent($item, $document, $indexableFile);
                $addDocumentUrlEvent = $this->eventDispatcher->dispatch($addDocumentUrlEvent);
                $document->setField('url', $addDocumentUrlEvent->getUrl() ?? $publicUrl);

                $fields = $document->getFields();
                $modifyAccessEvent = new ModifyAccessEvent($fields['access'] ?? '', $item, $indexableFile);
                $modifyAccessEvent = $this->eventDispatcher->dispatch($modifyAccessEvent);
                $accessRootline = $modifyAccessEvent->getAccessRootline();
                $document->setField('access', $accessRootline);

                $event->setDocuments($documents);
            }
        }
    }

    protected function getIndexableFile(Item $item, $languageId): ?FileInterface
    {
        $indexableLanguage = $this->isIndexableLanguage($item, $languageId);

        $storedFile = $this->fetchFile($item);
        if ($storedFile instanceof FileInterface && $indexableLanguage) {
            return $storedFile;
        }

        return null;
    }

    protected function isIndexableLanguage(Item $item, int $sysLanguageUid): bool
    {
        $record = $item->getRecord();

        /** @var IndexItemRepository $indexItemRepository */
        $indexItemRepository = GeneralUtility::makeInstance(IndexItemRepository::class);
        $indexItem = $indexItemRepository->findIndexableItem(
            $record['uid'],
            $item->getRootPageUid(),
            $sysLanguageUid,
            $item->getType(),
            $item->getIndexingConfigurationName()
        );

        return !empty($indexItem);
    }

    protected function fetchFile(Item $item): ?FileInterface
    {
        $sysFileUid = (int)$item->getRecord()['file'];
        // @extensionScannerIgnoreLine
        if (array_key_exists($sysFileUid, $this->fileCache)) {
            // @extensionScannerIgnoreLine
            return $this->fileCache[$sysFileUid];
        }

        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        // @extensionScannerIgnoreLine
        $this->fileCache[$sysFileUid] = $fileRepository->findByUid($sysFileUid);
        return $this->fileCache[$sysFileUid];
    }

    protected function getFileContent(FileInterface $file): string
    {
        try {
            $service = ServiceFactory::getTika();
            if ($service instanceof SolrService) {
                $service->setSolrConnection($this->currentlyUsedSolrConnection);
            }
            $content = $service->extractText($file);
            $content = $this->cleanupContent($content);
        } catch (NoSolrConnectionFoundException $e) {
            $content = '';
        }

        return $content;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function cleanupContent($content): string
    {
        return trim($content ?? '');
    }

    protected function isLocalResource(FileInterface $file): bool
    {
        return $file->getStorage()->getDriverType() === 'Local';
    }
}
