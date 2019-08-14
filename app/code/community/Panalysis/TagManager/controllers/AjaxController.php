<?php

class Panalysis_Tagmanager_AjaxController extends Mage_Core_Controller_Front_Action 
{
    public function indexAction()
    {
        //echo "Hi";
        
    //$this->loadLayout();
    //$this->renderLayout();
    }
    
    public function getCheckoutStateAction() 
    {
        //echo "start";
    }
    
    public function checkAjaxAction()
    {
        $session = Mage::getSingleton('core/session');
        $ajax_flag = Mage::app()->getRequest()->getParam('ajax_layer', false);
        $ajax_request = Mage::app()->getRequest()->isXmlHttpRequest();
        $tmProduct = $session->getTmProduct();
        $ajax_product = $session->getData('panalysis_tagmanager', false);

        $data = array();
        if ($ajax_product && $ajax_flag && $ajax_request && $tmProduct)
        {
            $helper = Mage::helper('panalysis_tagmanager');
            
            $tmProduct = $session->getTmProduct();
            if($tmProduct) $code = $helper->buildAddToCartData($tmProduct);
            $session->unsTmProduct();
            $session->unsData('panalysis_tagmanager');

            $data['code'] = $code;
            $data['response'] = 'datalayer';        
       
        }else{
            $data['response'] = '';
        }
        
        $response = Mage::helper('core')->jsonEncode($data);
        
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
        $this->getResponse()->setBody($response);
    }

}
