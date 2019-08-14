<?php

class Panalysis_TagManager_Block_Adminhtml_Version
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return (string) Mage::helper('panalysis_tagmanager')->getExtensionVersion();
    }
}


