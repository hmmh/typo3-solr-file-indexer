<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AbstractWidget;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\AdditionalCssInterface;

/**
 * This widget will show the number of pages
 */
class IndexableDocumentsLanguageWidget extends AbstractWidget implements AdditionalCssInterface
{

    /**
     * @var string
     */
    protected $title = 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:widgets.indexableDocumentsLanguage.title';

    /**
     * @inheritDoc
     */
    protected $templateName = 'IndexableDocumentsLanguageWidget';

    /**
     * @inheritDoc
     */
    protected $height = 4;

    /**
     * @inheritDoc
     */
    protected $iconIdentifier = 'content-widget-number';

    protected function initializeView(): void
    {
        parent::initializeView();

        $this->view->assign('roots', GeneralUtility::makeInstance(WidgetService::class)->getIndexableDocuments());
    }

    /**
     * @return array
     */
    public function getCssFiles(): array
    {
        return ['EXT:solr_file_indexer/Resources/Public/Css/widget.css'];
    }
}
