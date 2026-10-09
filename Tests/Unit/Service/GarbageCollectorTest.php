<?php

declare(strict_types=1);

namespace HMMH\SolrFileIndexer\Tests\Unit\Service;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site as SolrSite;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use HMMH\SolrFileIndexer\Resource\IndexItemRepository;
use HMMH\SolrFileIndexer\Service\ConnectionAdapter;
use HMMH\SolrFileIndexer\Service\GarbageCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(GarbageCollector::class)]
final class GarbageCollectorTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private IndexItemRepository&MockObject $indexItemRepository;
    private QueueItemRepository&MockObject $queueItemRepository;
    private ConnectionAdapter&MockObject $connectionAdapter;
    private SolrConnection $connectionEn;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TCA']['tx_solrfileindexer_items']['ctrl']['languageField'] = 'sys_language_uid';

        $this->indexItemRepository = $this->createMock(IndexItemRepository::class);
        $this->queueItemRepository = $this->createMock(QueueItemRepository::class);

        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getSiteByRootPageId')->willReturn(self::createStub(Site::class));
        GeneralUtility::addInstance(SiteFinder::class, $siteFinder);

        $solrConfiguration = self::createStub(TypoScriptConfiguration::class);
        $solrConfiguration->method('getEnableCommits')->willReturn(true);
        $solrSite = self::createStub(SolrSite::class);
        $solrSite->method('getSolrConfiguration')->willReturn($solrConfiguration);
        $siteRepository = self::createStub(SiteRepository::class);
        $siteRepository->method('getSiteByPageId')->willReturn($solrSite);
        GeneralUtility::addInstance(SiteRepository::class, $siteRepository);

        $this->connectionEn = self::createStub(SolrConnection::class);
        $this->connectionAdapter = $this->createMock(ConnectionAdapter::class);
        $this->connectionAdapter->method('getConnectionsBySite')->willReturn([
            0 => self::createStub(SolrConnection::class),
            1 => $this->connectionEn,
        ]);
        GeneralUtility::setSingletonInstance(ConnectionAdapter::class, $this->connectionAdapter);
    }

    /**
     * Obsolete item of the English metadata translation 60 of file metadata 52.
     */
    private static function obsoleteEnglishItem(): array
    {
        return [
            'root' => 1,
            'item_type' => 'sys_file_metadata',
            'item_uid' => 52,
            'localized_uid' => 60,
            'sys_language_uid' => 1,
            'indexing_configuration' => 'sys_file_metadata',
        ];
    }

    /**
     * When only the localized metadata record changed, the file is still indexed: the queue item (shared by all
     * languages) is kept and re-indexing is forced, the document of the previous record is removed from Solr.
     */
    #[Test]
    public function queueItemIsKeptIfFileIsStillIndexed(): void
    {
        $this->indexItemRepository->method('findLockedEntries')->willReturn([self::obsoleteEnglishItem()]);
        $this->indexItemRepository->expects(self::once())->method('hasUnlockedItem')->with(52, 1, 'sys_file_metadata')->willReturn(true);

        $this->queueItemRepository->expects(self::never())->method('deleteItems');
        $this->queueItemRepository->expects(self::once())->method('updateItemsChangedTime')
            ->with(self::greaterThan(0), [], ['sys_file_metadata'], ['sys_file_metadata'], [52]);
        $this->connectionAdapter->expects(self::once())->method('deleteByQuery')
            ->with($this->connectionEn, 'type:sys_file_metadata AND uid:60');
        $this->connectionAdapter->expects(self::once())->method('commit');
        $this->indexItemRepository->expects(self::once())->method('removeObsoleteEntries');

        (new GarbageCollector($this->indexItemRepository, $this->queueItemRepository))->removeObsoleteEntriesFromIndexes(null);
    }

    /**
     * When the file is no longer part of any collection, it is removed from the queue and from Solr.
     */
    #[Test]
    public function queueItemIsDeletedIfFileIsNoLongerIndexed(): void
    {
        $this->indexItemRepository->method('findLockedEntries')->willReturn([self::obsoleteEnglishItem()]);
        $this->indexItemRepository->method('hasUnlockedItem')->willReturn(false);

        $this->queueItemRepository->expects(self::once())->method('deleteItems')
            ->with(self::isArray(), ['sys_file_metadata'], ['sys_file_metadata'], [52]);
        $this->queueItemRepository->expects(self::never())->method('updateItemsChangedTime');
        $this->connectionAdapter->expects(self::once())->method('deleteByQuery')
            ->with($this->connectionEn, 'type:sys_file_metadata AND uid:60');
        $this->connectionAdapter->expects(self::once())->method('commit');
        $this->indexItemRepository->expects(self::once())->method('removeObsoleteEntries');

        (new GarbageCollector($this->indexItemRepository, $this->queueItemRepository))->removeObsoleteEntriesFromIndexes(null);
    }
}
