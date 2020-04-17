<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AbstractBarChartWidget;

/**
 * This widget will show the number of pages
 */
class IndexableDocumentsDomainWidget extends AbstractBarChartWidget
{

    /**
     * @var string
     */
    protected $title = 'LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:widgets.indexableDocumentsChart.title';

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
    protected $templateName = 'IndexableDocumentsDomainWidget';


    protected function prepareChartData(): void
    {
        $labels = [];
        $languageLabels = [];
        $data = [];
        $backgroundColor = [];
        $color = 0;

        $roots = GeneralUtility::makeInstance(WidgetService::class)->getIndexableDocuments();
        foreach ($roots as $root) {
            $labels[] = $root['host'];
            $backgroundColor[] = $this->chartColors[$color++];
            $tmpLanguageLabels = [];
            $tmpCount = 0;
            foreach ($root['languages'] as $language) {
                if ($language['count'] > 0) {
                    $tmpCount += $language['count'];
                    $tmpLanguageLabels[] = $language['title'] . ': ' . $language['count'];
                }
            }

            $data[] = $tmpCount;
            $languageLabels[] = implode(', ', $tmpLanguageLabels);

            if ($color >= count($this->chartColors)) {
                $color = 0;
            }
        }

        $this->chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->getLanguageService()->sL('LLL:EXT:solr_file_indexer/Resources/Private/Language/locallang_db.xlf:widgets.indexableDocumentsChart.total'),
                    'backgroundColor' => $backgroundColor,
                    'data' => $data
                ]
            ],
        ];
    }
}
