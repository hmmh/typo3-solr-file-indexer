<?php
namespace HMMH\SolrFileIndexer\Indexer;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh multimediahaus AG
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Event\AddDocumentUrlEvent;
use HMMH\SolrFileIndexer\Event\ModifyContentEvent;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Resource\MetadataRepository;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\Service\ServiceFactory;
use HMMH\SolrFileIndexer\Service\SolrService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\FileProcessingAspect;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class FileIndexer
 *
 * @package HMMH\SolrFileIndexer\Indexer
 */
class FileIndexer extends Indexer
{
    /**
     * @var array
     */
    protected $fileCache = [];

    /**
     * @var ExtensionConfig
     */
    protected $extensionConfiguration;

    /**
     * @var ConnectionAdapter
     */
    protected $connectionAdapter;

    /**
     * Indexes an item from the indexing queue.
     *
     * @param Item $item An index queue item
     *
     * @return true
     */
    public function index(Item $item): bool
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('fileProcessing', new FileProcessingAspect(false));

        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfig::class);

        $this->type = $item->getType();
        $this->setLogging($item);

        $this->connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);

        $solrConnections = $this->connectionAdapter->getConnectionsBySite($item->getSite());
        foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
            $this->currentlyUsedSolrConnection = $solrConnection;
            // check whether we should move on at all
            $indexableFile = $this->getIndexableFile($item, (int)$systemLanguageUid);
            if ($indexableFile !== null) {
                $this->indexItem($item, $systemLanguageUid);
            }
        }

        return true;
    }

    /**
     * Converts an item array (record) to a Solr document by mapping the
     * record's fields onto Solr document fields as configured in TypoScript.
     *
     * @param Item $item An index queue item
     * @param int $language Language Id
     * @return Document|null The Solr document converted from the record
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     * @throws ImmediateResponseException
     */
    protected function itemToDocument(Item $item, int $language = 0): ?Document
    {
        $content = '';

        $document = parent::itemToDocument($item, $language);
        if (!($document instanceof Document)) {
            $document = parent::itemToDocument($item, 0);
        }
        $baseContent = $document['content'];
        if (!empty($baseContent) && is_string($baseContent)) {
            $content .= $baseContent;
        }
        try {
            $content .= $this->txtFromFile($this->fetchFile($item));
        } catch (\Solarium\Exception\RuntimeException $re) {
            return null;
        }

        $event = new ModifyContentEvent($content);
        $event = $this->eventDispatcher->dispatch($event);
        $content = $event->getContent();

        $document->setField('content', $content);
        $url = $document['url'];
        if (empty($url)) {
            $this->addDocumentUrl($item, $document);
        }

        return $document;
    }

    /**
     * @param File $file
     *
     * @return string
     * @throws \TYPO3\CMS\Core\Package\Exception\UnknownPackageException
     */
    protected function txtFromFile(File $file): string
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
     * @param Item $item
     * @param int $languageId
     *
     * @return mixed|null
     */
    protected function getIndexableFile(Item $item, $languageId)
    {
        $indexableLanguage = $this->setRecordForLanguage($item, $languageId);

        $storedFile = $this->fetchFile($item);
        if ($storedFile !== null && $indexableLanguage) {
            return $storedFile;
        }

        return null;
    }

    /**
     * @param Item $item
     * @param int  $sysLanguageUid
     *
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    protected function setRecordForLanguage(Item $item, int $sysLanguageUid): bool
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

        if (empty($indexItem)) {
            return false;
        }

        if ($sysLanguageUid > 0 && $indexItem['item_uid'] !== $indexItem['localized_uid']) {
            $translatedRecord = BackendUtility::getRecord(MetadataRepository::FILE_TABLE, $indexItem['localized_uid']);
            if (!empty($translatedRecord)) {
                $item->setRecord($translatedRecord);
            }
        }

        return true;
    }

    /**
     * @param Item $item
     *
     * @return FileInterface|null
     */
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

    /**
     * @param Item     $item
     * @param Document $document
     *
     * @return void
     */
    protected function addDocumentUrl(Item $item, Document $document): void
    {
        $file = $this->fetchFile($item);
        if ($file instanceof FileInterface) {
            $publicUrl = $file->getPublicUrl();
            if ($this->isLocalResource($file)) {
                if (str_starts_with($publicUrl, '/')) {
                    $publicUrl = ltrim($publicUrl, '/');
                }
                $publicUrl = $this->extensionConfiguration->getLocalPrefix() . $publicUrl;
            }
            $event = new AddDocumentUrlEvent($item, $document, $file);
            $event = $this->eventDispatcher->dispatch($event);
            $document->setField('url', $event->getUrl() ?? $publicUrl);
        }
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

    protected function resolveFieldValue(
        array $indexingConfiguration,
        string $solrFieldName,
        array $data,
        TypoScriptFrontendController $tsfe,
        int|SiteLanguage $language,
    ): mixed {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? GeneralUtility::makeInstance(Tsfe::class)
            ->getServerRequestForTsfeByPageIdAndLanguageId(
                $tsfe->id,
                $language instanceof SiteLanguage ? $language->getLanguageId() : $language
            );

        if ($request->getAttribute('language') === null) {
            if (!($language instanceof SiteLanguage)) {
                $site = $request->getAttribute('site');
                $language = $site->getLanguageById($language);
            }
            $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute('language', $language);
        }

        return parent::resolveFieldValue($indexingConfiguration, $solrFieldName, $data, $tsfe, $language);
    }

    protected function getPageIdOfItem(Item $item): ?int
    {
        if ($item->getType() === 'sys_file_metadata') {
            return $item->getRootPageUid();
        }
        return parent::getPageIdOfItem($item);
    }

    protected function isLocalResource(FileInterface $file): bool
    {
        return $file->getStorage()->getDriverType() === 'Local';
    }
}
