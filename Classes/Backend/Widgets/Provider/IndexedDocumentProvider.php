<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets\Provider;

use HMMH\SolrFileIndexer\Service\Widgets\IndexingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

/**
 * Class IndexedDocumentProvider
 *
 * @package HMMH\SolrFileIndexer\Backend\Widgets\Provider
 */
class IndexedDocumentProvider implements ChartDataProviderInterface
{
    /**
     * @var array
     */
    protected $chartColors = ['#ff8700', '#a4276a', '#1a568f', '#4c7e3a', '#69bbb5', '#fe5e51', '#414c50', '#36abb5'];

    /**
     * @inheritDoc
     */
    public function getChartData(): array
    {
        $labels = [];
        $data = [];
        $backgroundColor = [];
        $color = 0;

        $cores = GeneralUtility::makeInstance(IndexingService::class)->getIndexedDocuments();

        foreach ($cores as $core) {
            $labels[] = $core['options']['core'];
            $data[] = $core['numFound'];
            $backgroundColor[] = $this->chartColors[$color++];

            if ($color >= count($this->chartColors)) {
                $color = 0;
            }
        }

        return [
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
