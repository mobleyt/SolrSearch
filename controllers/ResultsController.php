<?php

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


class SolrSearch_ResultsController
    extends Omeka_Controller_AbstractActionController
{

    /**
     * Cache the facets table.
     */
    public function init()
    {
        $this->_fields = $this->_helper->db->getTable('SolrSearchField');
    }


    /**
     * Intercept queries from the simple search form.
     */
    public function interceptorAction()
    {
        $this->_redirect('solr-search?'.http_build_query(array(
            'q' => $this->_request->getParam('query')
        )));
    }

    /**
     * Display Solr results.
     */
    public function indexAction()
    {

        // Get pagination settings.
        $limit  = $this->_request->limit ? $this->_request->limit : get_option('per_page_public');
        $page  = $this->_request->page ? $this->_request->page : 1;
        $facet_page  = $this->_request->facet_page ? $this->_request->facet_page : 1;
        $facet_offset  = $this->_request->facet_offset ? $this->_request->facet_offset : 0;
        $facet_sort  = $this->_request->facet_sort ? $this->_request->facet_sort : 'count';
        $sort  = $this->_request->sort ? $this->_request->sort : 'score desc';
        $start = ($page-1) * $limit;
        // Execute the query.
        $qf  = $this->_request->qf ? $this->_request->qf : 'text';
        $results = $this->_search($start, $limit, $facet_page, $facet_offset, $facet_sort, $sort, $qf);


        // Set the pagination.
        Zend_Registry::set('pagination', array(
            'page'          => $page,
            'total_results' => $results->response->numFound,
            'per_page'      => $limit
        ));

        // Push results to the view.
        $this->view->results = $results;

    }

    public function resultSet()
    {

        // Get pagination settings.
        $limit  = $this->_request->limit ? $this->_request->limit : get_option('per_page_public');
        $page  = $this->_request->page ? $this->_request->page : 1;
        $facet_page  = $this->_request->facet_page ? $this->_request->facet_page : 1;
        $facet_offset  = $this->_request->facet_offset ? $this->_request->facet_offset : 0;
        $facet_sort  = $this->_request->facet_sort ? $this->_request->facet_sort : 'count';
        $sort  = $this->_request->sort ? $this->_request->sort : 'score desc';
        $start = ($page-1) * $limit;
        // Execute the query.
        $qf  = $this->_request->qf ? $this->_request->qf : 'text';
        $results = $this->_search($start, $limit, $facet_page, $facet_offset, $facet_sort, $sort, $qf);


       return $results;

    }



    /**
     * Pass setting to Solr search
     *
     * @param int $offset Results offset
     * @param int $limit  Limit per page
     * @return SolrResultDoc Solr results
     */
    protected function _search($offset, $limit, $facet_page, $facet_offset, $facet_sort, $sort, $qf)
    {

        // Connect to Solr.
        $solr = SolrSearch_Helpers_Index::connect();

        // Get the parameters.
        $params = $this->_getParameters($facet_page, $facet_offset, $facet_sort, $sort, $qf);

        // Construct the query.
        $query = $this->_getQuery();

        // Execute the query.
        return $solr->search($query, $offset, $limit, $params);

    }


    /**
     * Form the complete Solr query.
     *
     * @return string The Solr query.
     */
    protected function _getQuery()
    {

        // Get the `q` GET parameter.
        $query = $this->_request->q;

        // If defined, replace `:`; otherwise, revert to `*:*`
        if (!empty($query)) $query = str_replace(':', ' ', $query);
        else $query = '*:*';

        // Get the `facet` GET parameter
        $facet = $this->_request->facet;

        // Form the composite Solr query.
        if (!empty($facet)) $query .= " AND {$facet}";

        return $query;

    }


    /**
     * Construct the Solr search parameters.
     *
     * @return array Array of fields to pass to Solr
     */
    protected function _getParameters($facet_page, $facet_offset, $facet_sort, $sort, $qf)
    {

        // Get a list of active facets.
        $facets = $this->_fields->getActiveFacetKeys();

		if($facet_page == 'next'){$facet_offset = $facet_offset + 10;}
		elseif($facet_page == 'prev' && $facet_offset >= 10){$facet_offset = $facet_offset - 10;}
		else{$facet_offset == 0;}

        return array(

            'defType'        => 'edismax',
            'q.alt'          => '*:*',
            'qf'             => $qf,
            'facet'          => 'true',
            'facet.field'    => $facets,
            'facet.offset'   => $facet_offset,
            'facet.mincount' => 1,
            'facet.limit'    => get_option('solr_search_facet_limit'),
            'facet.sort'     => $facet_sort,
            'hl'             => get_option('solr_search_hl')?'true':'false',
            'hl.snippets'    => get_option('solr_search_hl_snippets'),
            'hl.fragsize'    => get_option('solr_search_hl_fragsize'),
            'hl.fl'          => '*_t',
            'sort'          => $sort

        );

    }


}
