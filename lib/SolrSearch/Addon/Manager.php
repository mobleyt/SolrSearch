<?php
/**
 * SolrSearch Omeka Plugin helpers.
 *
 * Default helpers for the SolrSearch plugin
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at http://www.apache.org/licenses/LICENSE-2.0 Unless required by
 * applicable law or agreed to in writing, software distributed under the
 * License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS
 * OF ANY KIND, either express or implied. See the License for the specific
 * language governing permissions and limitations under the License.
 *
 * @package    omeka
 * @subpackage SolrSearch
 * @author     "Scholars Lab"
 * @copyright  2010 The Board and Visitors of the University of Virginia
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @version    $Id$
 * @link       http://www.scholarslab.org
 *
 * PHP version 5
 *
 */

/**
 * This manages the process of getting the addon information from the config files and using 
 * them to index a document.
 **/
class SolrSearch_Addon_Manager
{
    // {{{Properties

    /**
     * The database this will interface with.
     *
     * @var Omeka_Db
     **/
    var $db;

    /**
     * The addon directory.
     *
     * @var string
     **/
    var $addonDir;

    /**
     * The parsed addons
     *
     * @var array of SolrSearch_Addon_Addon
     **/
    var $addons;

    // }}}
    // {{{Methods

    /**
     * This instantiates a SolrSearch_Addon_Manager
     *
     * @param Omeka_Db $db       The database to initialize everything with.
     * @param string   $addonDir The directory for the addon config files.
     **/
    function __construct($db, $addonDir=null)
    {
        $this->db       = $db;
        $this->addonDir = $addonDir;
        $this->addons   = null;

        if ($this->addonDir === null) {
            $this->addonDir = SOLR_SEARCH_PLUGIN_DIR . '/addons';
        }
    }

    /**
     * This parses all the JSON configuration files in the addon directory and 
     * returns the addons.
     *
     * @param SolrSearch_Addon_Config $config The configuration parser. If 
     * null, this is created.
     *
     * @return array of SolrSearch_Addon_Addon
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public function parseAll($config=null)
    {
        if (is_null($config)) {
            $config = new SolrSearch_Addon_Config($this->db);
        }
        if (is_null($this->addons)) {
            $this->addons = array();
        }

        $this->addons = array_merge(
            $this->addons, $config->parseDir($this->addonDir)
        );

        return $this->addons;
    }

    /**
     * A helper method to the return the addon for the record.
     *
     * @param Omeka_Record $record The record to find an addon for.
     *
     * @return SolrSearch_Addon_Addon|null $addon The addon for the input 
     * record.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public function findAddonForRecord($record)
    {
        $hit = null;

        $recordTable = get_class($record->getTable());
        foreach ($this->addons as $key => $addon) {
            $tableName = get_class($this->db->getTable($addon->table));
            if ($tableName == $recordTable) {
                $hit = $addon;
                break;
            }
        }

        return $hit;
    }

    /**
     * This reindexes all the addons and returns the Solr documents created.
     *
     * @param SolrSearch_Addon_Config $config The configuration parser. If 
     * null, this is created. If given, this forces the Addons to be re-parsed; 
     * otherwise, they're only re-parsed if they haven't been yet.
     *
     * @return array of Apache_Solr_Document $docs The documents generated by 
     * indexing the Addon records.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public function reindexAddons($config=null)
    {
        $docs = array();
        $idxr = new SolrSearch_Addon_Indexer($this->db);

        if (is_null($this->addons) || !is_null($config)) {
            $this->parseAll($config);
        }

        $docs = $idxr->indexAll($this->addons);

        return $docs;
    }

    /**
     * This indexes a single record.
     *
     * @param Omeka_Record $record The record to index.
     * @param SolrSearch_Addon_Config $config The configuration parser. If 
     * null, this is created. If given, this forces the Addons to be re-parsed; 
     * otherwise, they're only re-parsed if they haven't been yet.
     *
     * @return Apache_Solr_Document|null $doc The indexed document or null, if 
     * the record's not to be indexed.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public function indexRecord($record, $config=null)
    {
        $doc  = null;
        $idxr = new SolrSearch_Addon_Indexer($this->db);

        if (is_null($this->addons) || !is_null($config)) {
            $this->parseAll($config);
        }

        $addon = $this->findAddonForRecord($record);
        if (!is_null($addon) && $idxr->isRecordIndexed($record, $addon)) {
            $doc = $idxr->indexRecord($record, $addon);
        }

        return $doc;
    }

    /**
     * This returns the Solr ID for the record.
     *
     * @param Omeka_Record $record The record to index.
     * @param SolrSearch_Addon_Config $config The configuration parser. If 
     * null, this is created. If given, this forces the Addons to be re-parsed; 
     * otherwise, they're only re-parsed if they haven't been yet.
     *
     * @return string|null
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public function getId($record, $config=null)
    {
        $id   = null;
        $idxr = new SolrSearch_Addon_Indexer($this->db);

        if (is_null($this->addons) || !is_null($config)) {
            $this->parseAll($config);
        }

        $addon = $this->findAddonForRecord($record);
        if (!is_null($addon)) {
            $id = "{$addon->table}_{$record->id}";
        }

        return $id;
    }

    // }}}

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
