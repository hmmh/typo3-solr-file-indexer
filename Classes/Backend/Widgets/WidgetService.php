<?php
declare(strict_types = 1);
namespace HMMH\SolrFileIndexer\Backend\Widgets;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WidgetService
{
    /**
     * @return array
     */
    public function getIndexableDocuments(): array
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_metadata');

        $result = $queryBuilder->select('uid', 'enable_indexing', 'sys_language_uid')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->neq(
                    'enable_indexing',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                )
            )
            ->execute()
            ->fetchAll();

        $roots = $this->getSiteRoots();

        foreach ($result as $indexItem) {
            $pages = explode(',', $indexItem['enable_indexing']);
            foreach ($pages as $page) {
                if (isset($roots[$page], $roots[$page]['languages'][$indexItem['sys_language_uid']])) {
                    $roots[$page]['languages'][$indexItem['sys_language_uid']]['count']++;
                }
            }
        }

        ksort($roots);

        return $roots;
    }

    /**
     * @return array
     */
    protected function getSiteRoots(): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        $roots = [];

        foreach ($sites as $site) {
            /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
            $roots[$site->getRootPageId()] = [
                'host' => $site->getBase()->getHost(),
                'languages' => $this->getSiteLanguages($site->getLanguages())
            ];
        }

        return $roots;
    }

    /**
     * @param array $languages
     *
     * @return array
     */
    protected function getSiteLanguages(array $languages): array
    {
        $lang = [];

        foreach ($languages as $language) {
            /** @var \TYPO3\CMS\Core\Site\Entity\SiteLanguage $language */
            $lang[$language->getLanguageId()] = [
                'title' => $language->getTitle(),
                'count' => 0
            ];
        }

        return $lang;
    }
}
