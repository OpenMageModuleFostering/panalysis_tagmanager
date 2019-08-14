<?php

class Panalysis_TagManager_Model_Tagmanager extends Mage_Core_Model_Abstract
{
    
    public $checkoutState = "";
    public $categoryProducts = array();
    private $__cachedCategories = array();

    public function getAttributes($product)
    {
        $eavConfig = Mage::getModel('eav/config');
        $attributes = $eavConfig->getEntityAttributeCodes(
            Mage_Catalog_Model_Product::ENTITY,
            $product
        );
        return $attributes;
    }

    /**
     * @param $product
     * @return mixed
     */
    public function getPrice($product_id, $currency='default')
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product_id); 
        
        $priceModel = $product->getPriceModel();
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            list($minimalPrice, $maximalPrice) = $priceModel->getTotalPrices($product, null, null, false);
            $price = $minimalPrice;
        } elseif ($product->isGrouped()) {
            $prices = array();
            foreach ($product->getTypeInstance(true)->getAssociatedProducts($product) as $assoProd) {
                $prices[] = $assoProd->getFinalPrice();
            }
            $price = min($prices);
        } else {
            $price = $product->getFinalPrice();
        }

        if(Mage::helper('panalysis_tagmanager')->getUseMultipleCurrencies() || $currency=='user')
            $price = Mage::helper('core')->currency($price, false, false);
        
        $final_price = number_format($price, 2);
        
        return $final_price;
    }


    public function getBrand($product)
    {

        $brand = '';
        //$product = Mage::getModel('catalog/product')->load($product->getID());
        $brandAttr = Mage::helper('panalysis_tagmanager')->getBrandCode();
        $attributes = $this->getAttributes($product);
        
        if (in_array($brandAttr, $attributes) && @$product->getAttributeText($brandAttr)) {
            $brand = @$product->getAttributeText($brandAttr);
        } else {
            if (in_array('manufacturer', $attributes)) {
                $brand = @$product->getAttributeText('manufacturer');
            }
        }
        return $brand;
    }

    /*
     * (string) getCategory()
     * Extracts the category for either a specific product or from the current 
     * category in the registry
     */
    public function getCategory($product=null)
    {
        // check whether there is a current category
        $category = Mage::registry('current_category');
        if (! isset($category) && isset($product)) {
            // if there isn't a current category and a product has been supplied
            // then get the first category listed for that product
            $category = $product->getCategoryCollection()
                ->addAttributeToSelect('name')
                ->getFirstItem();
        }
        elseif(! isset($category)) {
            // otherwise return an empty string
            return "";
        }
        
        // if we have made it this far then create the string category path
        $catPath = '';
        $pathIds = $category->getPathIds();
        $_key = implode($pathIds, "-");
        if(array_key_exists($_key, $this->__cachedCategories)){
            return $this->__cachedCategories[$_key];
        }
        
        // exclude the root level categories from the list
        $root_id = Mage::app()->getStore()->getRootCategoryId();
        $is_ok = false;
        $idList = array();
        foreach($pathIds as $i){
            if($i == $root_id){
                $is_ok = true;
                continue;
            }
            elseif($is_ok==false){
                continue;
            }
            else
            {
                $idList[] = $i;
            }
        }

        // load the data in a single query to save resources
        $coll = $category->getResourceCollection();
        $coll->addAttributeToSelect('name');
        $coll->addAttributeToFilter('entity_id', array('in' => $idList));
        $i=0;
        foreach ($coll as $cat) {
            if($i>0) $catPath .= " / ";
            $i++;
            $catPath .= $cat->getName();
        }
        
        $this->__cachedCategories[$_key] = $catPath;
        return $catPath;
    }

    public function getCatArray($product)
    {
        $cateNames = array();
        $product = Mage::getModel('catalog/product')->load($product->getId());
        $categoryCollection = $product->getCategoryCollection()->addAttributeToSelect('name');
            
        foreach($categoryCollection as $category)
        {
            $cateNames[] = $category->getName();
        }
            
        return $cateNames;
    }

    public function setCheckoutState($state){
        $this->state = $state;
    }
    
    public function getCheckoutState(){
        return $this->state;
    }
    
    public function setCategoryProducts($list){
        $this->categoryProducts = $list;
    }
    
    public function getCategoryProducts() {
        return $this->categoryProducts;
    }
    
    // the following function is modified from https://github.com/CVM/Magento_GoogleTagManager

    public function getVisitorData()
    {
        $customer = Mage::getSingleton('customer/session');
        return $this->getVisitorOrderData($customer);
    }
    
    //check if user placed orders before and get total
    private function getVisitorOrderData($customer = false)
    {
        $data = array();
        $orders = false;
        
        if(!$customer) $customer = Mage::getSingleton('customer/session');
        $customerId = $customer->getCustomerId();
        if ($customerId > 0) $data['customerId'] = (string) $customerId;
        
        if(Mage::getSingleton('customer/session')->isLoggedIn())
        {
            $orders = Mage::getResourceModel('sales/order_collection')->addFieldToSelect('grand_total')->addAttributeToSelect('created_at')->addFieldToFilter('customer_id',$customer->getId());
            $data['customerGroup'] = (string)Mage::getModel('customer/group')->load($customer->getCustomerGroupId())->getCode();
            $data['visitorExistingCustomer'] = 'Yes';
        }else{
            
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            
            $email = $quote->getBillingAddress()->getEmail();
            $data['visitorExistingCustomer'] = 'No';

            if($email)
            {
                $orders = Mage::getModel('sales/order')->getCollection();
                $orders->addFieldToSelect('grand_total');
                $orders->addFieldToFilter('customer_email', $email);
                
                if($orders) $data['visitorExistingCustomer'] = 'Yes';
            }
        }
        
        $ordersTotal = 0;
        $numOrders = 0;

        if($orders)
        {
            foreach ($orders as $order)
            {
                $ordersTotal += floatval($order->getGrandTotal());
                $numOrders ++;
            }
        }
        
        if($customerId > 0) {
            $data['visitorLifetimeValue'] = $this->convertCurrency($ordersTotal);
            $data['visitorLifetimeOrders'] = $numOrders; 
        }


        return $data;
    }
    
    private function convertCurrency($price)
    {
        $from = Mage::app()->getStore()->getBaseCurrencyCode();
        $to = Mage::app()->getStore()->getCurrentCurrencyCode();
        
        if($from != $to)
        {
            $price = Mage::helper('directory')->currencyConvert($price, $from, $to);
            $price = number_format($price, 2);
        }
        
        return $price;
    }
}