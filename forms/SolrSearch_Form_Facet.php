<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 cc=80; */

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


class SolrSearch_Form_Facet extends Omeka_Form
{

    /**
     * Construct the field indexing configuration form.
     */
    public function init()
    {
        $_db = get_db();
        $_facetsTable = $_db->getTable('SolrSearchFacet');

        $this->setMethod('post');
        $this->setAction('update');
        $this->setAttrib('id', 'facets-form');
        $this->setElementsBelongTo("facets");

        $g = 0;
        $n = 1000;
        $groups = $_facetsTable->getFacetsGroupedBySet();
        foreach ($groups as $title => $facets) {

            // Sub-form for group:
            $sf = new Zend_Form_SubForm();
            $sf->setLegend($title);
            $this->addSubForm($sf, "$g");

            foreach ($facets as $facet) {

                // Sub-sub-form for facet:
                $ssf = new Zend_Form_SubForm();
                $ssf->setElementsBelongTo("facets[$n]");
                $sf->addSubForm($ssf, "$n");

                $values = array();
                foreach (array('is_displayed', 'is_facet') as $key) {
                    if ($facet->$key == 1) array_push($values, $key);
                }

                $ssf->addElement('hidden', 'facetid', array(
                    'value' => $facet->id
                ));

                $ssf->addElement('text', 'label', array(
                    'value'    => $facet->label,
                    'revertto' => $facet->getOriginalLabel()
                ));

                $ssf->addElement('MultiCheckbox', 'options', array(
                    'multiOptions' => array(
                        'is_displayed' => 'Is Searchable',
                        'is_facet'     => 'Is Facet'
                    ),
                    'value' => $values
                ));

                $n++;

            }

            $g++;

        }

        $this->addElement( 'submit', 'submit', array(
            'label' => __('Update Search Fields')
        ));

    }

}
