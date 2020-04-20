<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AbstractDoughnutChartWidget;

/**
 * This widget will show the number of pages
 */
class IndexedDocumentsWidget extends AbstractDoughnutChartWidget
{

    /**
     * @var string
     */
    protected $title = 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:widgets.indexedDocuments.title';

    /**
     * @inheritDoc
     */
    protected $height = 4;

    /**
     * @var array
     */
    protected $chartColors = ['#ff8700', '#a4276a', '#1a568f', '#4c7e3a', '#69bbb5', '#fe5e51', '#414c50', '#36abb5'];

    /**
     * @inheritDoc
     */
    protected $templateName = 'SimpleChartWidget';

    protected function prepareChartData(): void
    {
        $labels = [];
        $data = [];
        $backgroundColor = [];
        $color = 0;

        $cores = GeneralUtility::makeInstance(WidgetService::class)->getIndexedDocuments();

        foreach ($cores as $core) {
            $labels[] = $core['options']['core'];
            $data[] = $core['numFound'];
            $backgroundColor[] = $this->chartColors[$color++];

            if ($color >= count($this->chartColors)) {
                $color = 0;
            }
        }

        $this->chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'backgroundColor' => $backgroundColor,
                    'data' => $data
                ]
            ],
        ];
    }
}
