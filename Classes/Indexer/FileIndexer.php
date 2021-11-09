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

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use HMMH\SolrFileIndexer\Configuration\ExtensionConfig;
use HMMH\SolrFileIndexer\Interfaces\AddContentInterface;
use HMMH\SolrFileIndexer\Interfaces\CleanupContentInterface;
use HMMH\SolrFileIndexer\Interfaces\DocumentUrlInterface;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\Service\ServiceFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileIndexer
 *
 * @package HMMH\SolrFileIndexer\Indexer
 */
class FileIndexer extends Indexer
{
    const FILE_TABLE = 'sys_file_metadata';

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
    public function index(Item $item)
    {
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfig::class);

        $this->type = $item->getType();
        $this->setLogging($item);

        $this->connectionAdapter = GeneralUtility::makeInstance(ConnectionAdapter::class);

        $solrConnections = $this->connectionAdapter->getConnectionsBySite($item->getSite());
        foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
            $this->solr = $solrConnection;
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
        if (!($document instanceof Document) && $this->extensionConfiguration->ignoreLocalization()) {
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

        $content = $this->emitPostAddContentAfterSignal($document, $content);

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
    protected function txtFromFile(File $file)
    {
        try {
            $service = ServiceFactory::getTika();
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
        $record = $item->getRecord();
        $rootPage = $item->getRootPageUid();
        $allowedRootPages = empty($record['enable_indexing']) ? [] : GeneralUtility::trimExplode(',', $record['enable_indexing']);
        $indexableLanguage = $this->checkLanguageForIndexing($languageId, $record);

        $storedFile = $this->fetchFile($item);
        if ($storedFile !== null && $indexableLanguage && in_array($rootPage, $allowedRootPages)) {
            return $storedFile;
        }

        return null;
    }

    /**
     * @param Item $item
     *
     * @return File
     */
    protected function fetchFile(Item $item)
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
     * @param Item $item
     * @param Document $document
     */
    protected function addDocumentUrl(Item $item, Document $document)
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addDocumentUrl'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addDocumentUrl'] as $classReference) {
                $documentUrlObject = GeneralUtility::makeInstance($classReference);

                if ($documentUrlObject instanceof DocumentUrlInterface) {
                    $documentUrlObject->addDocumentUrl($item, $document);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($documentUrlObject) . ' must implement interface ' . DocumentUrlInterface::class,
                        1345807460
                    );
                }
            }
        } else {
            $file = $this->fetchFile($item);
            if ($file instanceof FileInterface) {
                $document->setField('url', $file->getPublicUrl());
            }
        }
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function cleanupContent($content): string
    {
        $content = trim($content);

        return $this->emitPostCleanContentSignal($content);
    }

    /**
     * @param $content
     *
     * @return string
     */
    protected function emitPostCleanContentSignal($content)
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['cleanupContent'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['cleanupContent'] as $classReference) {
                $cleanupObject = GeneralUtility::makeInstance($classReference);

                if ($cleanupObject instanceof CleanupContentInterface) {
                    $content = $cleanupObject->cleanup($content);
                }
            }
        }
        return $content;
    }

    /**
     * @param Document $document
     * @param string $content
     *
     * @return string
     */
    protected function emitPostAddContentAfterSignal(Document $document, $content)
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addContentAfter'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr_file_indexer']['addContentAfter'] as $classReference) {
                $addObject = GeneralUtility::makeInstance($classReference);

                if ($addObject instanceof AddContentInterface) {
                    $content = $addObject->add($content);
                }
            }
        }
        return $content;
    }

    /**
     * @param int $languageId
     * @param array $record
     *
     * @return bool
     */
    protected function checkLanguageForIndexing($languageId, $record)
    {
        $languageField = $this->getLanguageField();
        $indexableLanguage = (int)$record[$languageField] === $languageId;

        if ($this->extensionConfiguration->ignoreLocalization() === true &&
            $languageId > 0 &&
            $indexableLanguage === false &&
            (int)$record[$languageField] === 0
        ) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::FILE_TABLE);
            $metadata = $queryBuilder->select('uid')
                ->from(self::FILE_TABLE)
                ->where(
                    $queryBuilder->expr()->eq(
                        $this->getLanguageParentField(),
                        $queryBuilder->createNamedParameter($record['uid'], \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        $languageField,
                        $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT)
                    )
                )
                ->setMaxResults(1)
                ->execute()
                ->fetchAssociative();

            if (empty($metadata['uid'])) {
                $indexableLanguage = true;
            }
        }

        if ($languageId > 0) {
            if ((int)$record[$languageField] > 0) {
                $this->removeOriginalFromIndex($record[$this->getLanguageParentField()]);
            } else {
                $this->removeOriginalFromIndex($record['uid']);
            }
        }

        return $indexableLanguage;
    }

    /**
     * @param int $uid
     */
    protected function removeOriginalFromIndex($uid)
    {
        $this->connectionAdapter->deleteByQuery($this->solr, 'type:' . self::FILE_TABLE . ' AND uid:' . (int)$uid);
    }

    /**
     * @return string
     */
    protected function getLanguageField()
    {
        return $GLOBALS['TCA'][self::FILE_TABLE]['ctrl']['languageField'];
    }

    /**
     * @return string
     */
    protected function getLanguageParentField()
    {
        return $GLOBALS['TCA'][self::FILE_TABLE]['ctrl']['transOrigPointerField'];
    }
}
