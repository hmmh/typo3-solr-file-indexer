<?php
namespace HMMH\SolrFileIndexer\Service;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2019 Sascha Wilking <sascha.wilking@hmmh.de>, hmmh
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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use HMMH\SolrFileIndexer\Base;
use \ApacheSolrForTypo3\Solr\ConnectionManager;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class ConnectionAdapter
 *
 * @package HMMH\SolrFileIndexer\Service
 */
class ConnectionAdapter implements SingletonInterface
{

    /**
     * @var ConnectionManager
     */
    protected $connectionManager = null;

    /**
     * @return ConnectionManager
     */
    public function getConnectionManager()
    {
        if ($this->connectionManager === null) {
            $this->connectionManager = Base::getObjectManager()->get(ConnectionManager::class);
        }

        return $this->connectionManager;
    }

    /**
     * @param int $pageId
     *
     * @return \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection
     * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
     */
    public function getConnectionByPageId(int $pageId)
    {
        return $this->getConnectionManager()->getConnectionByPageId($pageId);
    }

    /**
     * @param Site $site
     *
     * @return \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection[]
     */
    public function getConnectionsBySite(Site $site)
    {
        return $this->getConnectionManager()->getConnectionsBySite($site);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection $solrConnection
     * @param \Solarium\QueryType\Extract\Query $query
     *
     * @return array
     */
    public function extractByQuery($solrConnection, $query)
    {
        return $this->getSolrWriteService($solrConnection)->extractByQuery($query);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection $solrConnection
     * @param bool $expungeDeletes
     * @param bool $waitSearcher
     */
    public function commit($solrConnection, $expungeDeletes = false, $waitSearcher = true)
    {
        $this->getSolrWriteService($solrConnection)->commit($expungeDeletes, $waitSearcher);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection $solrConnection
     * @param string $type
     * @param bool $commit
     */
    public function deleteByType($solrConnection, $type, $commit = true)
    {
        $this->getSolrWriteService($solrConnection)->deleteByType($type, $commit);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection $solrConnection
     * @param string $rawQuery
     */
    public function deleteByQuery($solrConnection, $rawQuery)
    {
        $this->getSolrWriteService($solrConnection)->deleteByQuery($rawQuery);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection $solrConnection
     *
     * @return \ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService
     */
    protected function getSolrWriteService($solrConnection)
    {
        return $solrConnection->getWriteService();
    }
}
