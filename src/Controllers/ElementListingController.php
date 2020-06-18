<?php

namespace Heggsta\ElementalListing\Controllers;

use DNADesign\Elemental\Controllers\ElementController;

class ElementListingController extends ElementController
{
    /**
     * @return string
     */
    public function getListing()
    {
        if (!$this->element->ID) {
            return '';
        }
            
        return $this->element->getListing($this->getActionParam());
    }

    public function getActionParam()
    {
        return self::has_curr() 
            ? self::curr()->getRequest()->latestParam('Action')
            : null;
    
    }
}