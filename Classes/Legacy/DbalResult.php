<?php

namespace HMMH\SolrFileIndexer\Legacy;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ForwardCompatibility\Result;

class DbalResult
{

    /**
     * @param Statement|Result|ResultStatement $result
     *
     * @return mixed
     */
    public static function fetch($result)
    {
        if (method_exists($result, 'fetchAssociative')) {
            return $result->fetchAssociative();
        } else {
            return $result->fetch();
        }
    }

    /**
     * @param Statement|Result|ResultStatement $result
     *
     * @return mixed
     */
    public static function fetchAll($result)
    {
        if (method_exists($result, 'fetchAllAssociative')) {
            return $result->fetchAllAssociative();
        } else {
            return $result->fetchAll();
        }
    }
}
