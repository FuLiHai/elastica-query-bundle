<?php

namespace Mapado\ElasticaQueryBundle;

use Elastica\Aggregation\AbstractAggregation;
use Elastica\Filter;
use Elastica\Filter\AbstractFilter;
use Elastica\Query\AbstractQuery;
use Elastica\Query as ElasticaQuery;

class QueryBuilder
{
    /**
     * documentManager
     *
     * @var DocumentManager
     * @access private
     */
    private $documentManager;

    /**
     * filterList
     *
     * @var array
     * @access private
     */
    private $filterList;

    /**
     * queryList
     *
     * @var array
     * @access private
     */
    private $queryList;

    /**
     * sortList
     *
     * @var array
     * @access private
     */
    private $sortList;

    /**
     * aggregationList
     *
     * @var array
     * @access private
     */
    private $aggregationList;

    /**
     * firstResults
     *
     * @var int
     * @access private
     */
    private $firstResults;

    /**
     * maxResults
     *
     * @var int
     * @access private
     */
    private $maxResults;

    /**
     * minScore
     *
     * @var int
     * @access private
     */
    private $minScore;

    /**
     * __construct
     *
     * @param DocumentManager $documentManager
     * @access public
     */
    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
        $this->filterList = [];
        $this->queryList = [];
        $this->sortList = [];
    }

    /**
     * addFilter
     *
     * @param AbstractFilter $filter
     * @access public
     * @return QueryBuilder
     */
    public function addFilter(AbstractFilter $filter)
    {
        $this->filterList[] = $filter;
        return $this;
    }

    /**
     * addQuery
     *
     * @param AbstractQuery $query
     * @access public
     * @return QueryBuilder
     */
    public function addQuery(AbstractQuery $query)
    {
        $this->queryList[] = $query;
        return $this;
    }

    /**
     * addSort
     *
     * @param mixed $sort Sort parameter
     * @access public
     * @return QueryBuilder
     */
    public function addSort($sort)
    {
        $this->sortList[] = $sort;
        return $this;
    }

    /**
     * addAggregation
     *
     * @param AbstractAggregation $aggregation
     * @access public
     * @return QueryBuilder
     */
    public function addAggregation(AbstractAggregation $aggregation)
    {
        $this->aggregationList[] = $aggregation;
        return $this;
    }

    /**
     * setMaxResults
     *
     * @param mixed $maxResults
     * @access public
     * @return QueryBuilder
     */
    public function setMaxResults($maxResults)
    {
        $this->maxResults = $maxResults;
        return $this;
    }

    /**
     * setFirstResults
     *
     * @param int $firstResults
     * @access public
     * @return QueryBuilder
     */
    public function setFirstResults($firstResults)
    {
        $this->firstResults = $firstResults;
        return $this;
    }

    /**
     * setMinScore
     *
     * @access public
     * @return QueryBuilder
     */
    public function setMinScore($minScore)
    {
        $this->minScore = $minScore;
        return $this;
    }

    /**
     * getElasticQuery
     *
     * @access public
     * @return Query
     */
    public function getElasticQuery()
    {
        if ($this->filterList) {
            $filteredQuery = new ElasticaQuery\Filtered($this->getQuery(), $this->getFilter());
            $query = new Query($filteredQuery);
        } else {
            $query = new Query($this->getQuery());
        }
        $query->setDocumentManager($this->documentManager);

        // manage size / from
        if ($this->firstResults) {
            $query->setFrom($this->firstResults);
        }
        if (isset($this->maxResults)) {
            $query->setSize($this->maxResults);
        }

        if (!empty($this->sortList)) {
            $query->setSort($this->sortList);
        }

        if (isset($this->minScore)) {
            $query->setMinScore($this->minScore);
        }

        if (!empty($this->aggregationList)) {
            foreach ($this->aggregationList as $aggregation) {
                $query->addAggregation($aggregation);
            }
        }

        return $query;
    }

    /**
     * getResult
     *
     * @access public
     * @return Elastica\ResultSet
     */
    public function getResult()
    {
        return $this->getElasticQuery()->getResult();
    }

    /**
     * getQuery
     *
     * @access private
     * @return \Elastica\Query\AbstractQuery
     */
    private function getQuery()
    {
        if (!$this->queryList) {
            return null;
        }

        if (count($this->queryList) == 1) {
            return current($this->queryList);
        }

        $query = new ElasticaQuery\BoolQuery();
        foreach ($this->queryList as $tmpQuery) {
            $query->addMust($tmpQuery);
        }

        return $query;
    }

    /**
     * getFilter
     *
     * @access private
     * @return AbstractFilter
     */
    private function getFilter()
    {
        if (!$this->filterList) {
            return null;
        }

        if (count($this->filterList) == 1) {
            return current($this->filterList);
        }


        $boolFilters = [];
        $andFilters = [];
        foreach ($this->filterList as $tmpFilter) {
            if ($this->isAndFilter($tmpFilter)) {
                $andFilters[] = $tmpFilter;
            } else {
                $boolFilters[] = $tmpFilter;
            }
        }

        $boolFilter = null;
        $nbBoolFilters = count($boolFilters);
        if ($nbBoolFilters > 1) {
            $boolFilter = new Filter\BoolFilter();
            foreach ($boolFilters as $tmpFilter) {
                $boolFilter->addMust($tmpFilter);
            }

            array_unshift($andFilters, $boolFilter);
        } elseif ($nbBoolFilters == 1) {
            $andFilters = array_merge($boolFilters, $andFilters);
        }

        $nbAndFilters = count($andFilters);
        if ($nbAndFilters == 1) {
            return current($andFilters);
        } elseif ($nbAndFilters > 1) {
            $filter = new Filter\BoolAnd();
            $filter->setFilters($andFilters);
            return $filter;
        }

        return null;
    }

    /**
     * select if the filter is more in a `BoolAnd` or a `BoolFilter`.
     * @see http://www.elasticsearch.org/blog/all-about-elasticsearch-filter-bitsets/
     *
     * @param Filter\AbstractFilter $filter
     * @access private
     * @return void
     */
    private function isAndFilter(Filter\AbstractFilter $filter)
    {
        $filterName = substr(get_class($filter), 16);

        return $filterName === 'Script'
            || $filterName === 'NumericRange'
            ||  substr($filterName, 0, 3) === 'Geo'
        ;
    }
}
