<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 cc=80; */

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


class SolrSearchPlugin extends Omeka_Plugin_AbstractPlugin
{


    // {{{ hooks
    protected $_hooks = array(
        'install',
        'uninstall',
        'initialize',
        'define_routes',
        'after_save_record',
        'after_save_item',
        'before_delete_record',
        'before_delete_item',
        'define_acl'
    );
    //}}}


    //{{{ filters
    protected $_filters = array(
        'admin_navigation_main',
        'search_form_default_action'
    );
    //}}}


    /**
     * Create the database tables, install the starting facets, and set the
     * default options.
     */
    public function hookInstall()
    {
        self::_createSolrTable();
        self::_installFacetMappings();
        self::_setOptions();
    }


    /**
     * Drop the database tables, flush the Solr index, and delete the options.
     */
    public function hookUninstall()
    {

        $sql = "DROP TABLE IF EXISTS `{$this->_db->prefix}solr_search_facets`";
        $this->_db->query($sql);

        try {
            $solr = new Apache_Solr_Service(
                get_option('solr_search_server'),
                get_option('solr_search_port'),
                get_option('solr_search_core')
            );
            $solr->deleteByQuery('*:*');
            $solr->commit();
            $solr->optimize();
        } catch (Exception $e) {
        }

        self::_deleteOptions();

    }


    /**
     * Register the string translations.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
    }


    /**
     * Register the application routes.
     *
     * @param array $args With `router`.
     */
    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig(new Zend_Config_Ini(
            SOLR_DIR.'/routes.ini'
        ));
    }


    /**
     * When a record is saved, try to extract and intex a Solr document.
     *
     * @param array $args With `record`.
     */
    public function hookAfterSaveRecord($args)
    {
        SolrSearch_Utils::ensureView();

        $record = $args['record'];
        $mgr = new SolrSearch_Addon_Manager($this->_db);
        $doc = $mgr->indexRecord($record);

        if (!is_null($doc)) {
            $solr = new Apache_Solr_Service(
                get_option('solr_search_server'),
                get_option('solr_search_port'),
                get_option('solr_search_core')
            );
            $solr->addDocuments(array($doc));
            $solr->commit();
            $solr->optimize();
        }
    }


    /**
     * When an item is saved, index the record if the item is set public, and
     * clear an existing record if it is set private.
     *
     * @param array $args With `record`.
     */
    public function hookAfterSaveItem($args)
    {
        SolrSearch_Utils::ensureView();

        $item = $args['record'];
        $solr = new Apache_Solr_Service(
            get_option('solr_search_server'),
            get_option('solr_search_port'),
            get_option('solr_search_core')
        );

        if ($item['public'] == true) {
            $docs = array();
            $doc = SolrSearch_Helpers_Index::itemToDocument($this->_db, $item);
            $docs[] = $doc;

            $solr->addDocuments($docs);
            $solr->commit();
            $solr->optimize();
        } else {
            // If the item's no longer public, remove it from the index.
            $solr->deleteById('Item_' . $item['id']);
            $solr->commit();
            $solr->optimize();
        }
    }


    /**
     * When a record is deleted, clear its Solr record.
     *
     * @param array $args With `record`.
     */
    public function hookBeforeDeleteRecord($args)
    {
        $record = $args['record'];
        $mgr = new SolrSearch_Addon_Manager($this->_db);
        $id = $mgr->getId($record);

        if (!is_null($id)) {
            $solr = new Apache_Solr_Service(
                get_option('solr_search_server'),
                get_option('solr_search_port'),
                get_option('solr_search_core')
            );
            try {
                $solr->deleteById($id);
                $solr->commit();
                $solr->optimize();
            } catch (Exception $e) {}
        }
    }


    /**
     * When an item is deleted, clear its Solr record.
     *
     * @param array $args With `record`.
     */
    public function hookBeforeDeleteItem($args)
    {
        $item = $args['record'];
        $solr = new Apache_Solr_Service(
            get_option('solr_search_server'),
            get_option('solr_search_port'),
            get_option('solr_search_core')
        );

        try {
            $solr->deleteById('Item_' . $item['id']);
            $solr->commit();
            $solr->optimize();
        } catch (Exception $e) {}
    }


    /**
     * Register the ACL.
     *
     * @param array $args With `acl`.
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        if (!$acl->has('SolrSearch_Config')) {
            $acl->addResource('SolrSearch_Config');
            $acl->allow(null, 'SolrSearch_Config', array('index', 'status'));
        }
    }


    /**
     * Add a link to the administrative navigation bar.
     *
     * @param string $nav The array of label/URI pairs.
     * @return array
     */
    public function filterAdminNavigationMain($nav)
    {
        if (is_allowed('SolrSearch_Config', 'index')) {
            $nav[] = array(
                'label' => __('Solr'), 'uri' => url('solr-search')
            );
        }
        return $nav;
    }


    /**
     * Override the default simple-search URI to automagically integrate into
     * the theme; leaves admin section alone for default search.
     *
     * @param string $uri URI for Simple Search.
     * @return string
     */
    public function filterSearchFormDefaultAction($uri)
    {
        if (!is_admin_theme()) $uri = url('solr-search/results/interceptor');
        return $uri;
    }


    // {{{protected


    /**
     * Install the facets table.
     */
    protected function _createSolrTable()
    {
        $this->_db->query(<<<SQL

        CREATE TABLE IF NOT EXISTS {$this->_db->prefix}solr_search_facets (

            id              int(10) unsigned NOT NULL auto_increment,
            element_id      int(10) unsigned,
            name            tinytext collate utf8_unicode_ci NOT NULL,
            label           tinytext collate utf8_unicode_ci NOT NULL,
            is_displayed    tinyint unsigned DEFAULT 0,
            is_facet        tinyint unsigned DEFAULT 0,

            PRIMARY KEY (id)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

SQL
);
    }


    /**
     * Install the default facet mappings.
     */
    protected function _installFacetMappings()
    {

        // Generic facets:
        $this->_installGenericFacet('tag', __('Tag'));
        $this->_installGenericFacet('collection', __('Collection'));
        $this->_installGenericFacet('itemtype', __('Item Type'));
        $this->_installGenericFacet('resulttype', __('Result Type'));

        // Element-backed facets:
        foreach ($this->_db->getTable('Element')->findAll() as $element) {
            $facet = new SolrSearchFacet($element);
            $facet->save();
        }

    }


    /**
     * Install the default facet mappings.
     *
     * @param string $name The facet `name`.
     * @param string $label The facet `label`.
     */
    protected function _installGenericFacet($name, $label)
    {
        $facet = new SolrSearchFacet();
        $facet->name            = $name;
        $facet->label           = $label;
        $facet->is_displayed    = 1;
        $facet->is_facet        = 1;
        $facet->save();
    }


    /**
     * Set the default global options.
     */
    protected function _setOptions()
    {
        set_option('solr_search_server', 'localhost');
        set_option('solr_search_port', '8080');
        set_option('solr_search_core', '/solr/collection1/');
        set_option('solr_search_rows', '');
        set_option('solr_search_facet_limit', '25');
        set_option('solr_search_hl', 'true');
        set_option('solr_search_snippets', '1');
        set_option('solr_search_fragsize', '250');
        set_option('solr_search_facet_sort', 'count');
    }


    /**
     * Delete the default global options.
     */
    protected function _deleteOptions()
    {
        delete_option('solr_search_server');
        delete_option('solr_search_port');
        delete_option('solr_search_core');
        delete_option('solr_search_rows');
        delete_option('solr_search_facet_limit');
        delete_option('solr_search_hl');
        delete_option('solr_search_snippets');
        delete_option('solr_search_fragsize');
        delete_option('solr_search_facet_sort');
    }


    //}}}


}
