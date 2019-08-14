<?php
class Panalysis_TagManager_Model_Config_Data_NotEmpty extends Mage_Core_Model_Config_Data
{
    public function _beforeSave()
    {
        $val = $this->getValue();
        if (empty($val)) {
            Mage::throwException($this->getFieldConfig()->label . " must not be empty");
        }
    }
}
