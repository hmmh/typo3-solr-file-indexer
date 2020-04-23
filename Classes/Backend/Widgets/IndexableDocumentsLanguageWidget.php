<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * This widget will show the number of pages
 */
class IndexableDocumentsLanguageWidget implements WidgetInterface, AdditionalCssInterface
{
    /**
     * @var WidgetConfigurationInterface
     */
    private $configuration;

    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var array
     */
    private $options;

    /**
     * IndexableDocumentsLanguageWidget constructor.
     *
     * @param WidgetConfigurationInterface $configuration
     * @param StandaloneView               $view
     * @param array                        $options
     */
    public function __construct(
        WidgetConfigurationInterface $configuration,
        StandaloneView $view,
        array $options = []
    ) {
        $this->configuration = $configuration;
        $this->view = $view;
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getCssFiles(): array
    {
        return ['EXT:solr_file_indexer/Resources/Public/Css/widget.css'];
    }

    /**
     * @inheritDoc
     */
    public function renderWidgetContent(): string
    {
        $this->view->setTemplate('Widget/IndexableDocumentsLanguageWidget');

        $widgetService = GeneralUtility::makeInstance(WidgetService::class);

        $this->view->assignMultiple([
            'icon' => $this->options['icon'],
            'title' => $this->options['title'],
            'roots' => $widgetService->getIndexableDocuments(),
            'options' => $this->options,
            'configuration' => $this->configuration
        ]);

        return $this->view->render();
    }
}
