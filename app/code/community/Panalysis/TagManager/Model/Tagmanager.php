<?php

class Panalysis_TagManager_Model_Tagmanager extends Mage_Core_Model_Abstract
{
    
    public $checkoutState = "";
    public $categoryProducts = array();

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
    public function getPrice($product_id)
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

        $price = Mage::helper('core')->currency($price, false, false);
        $final_price = Mage::app()->getStore()->roundPrice($price);
        
        return $final_price;
    }


    public function getBrand($product)
    {
        $brand = '';
	$product = Mage::getModel('catalog/product')->load($product->getID());
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

    public function getVariant($product)
    {
        $color = '';
	$product = Mage::getModel('catalog/product')->load($product->getID());
        $colorAttr = Mage::helper('panalysis_tagmanager')->getColorCode();
        $attributes = $this->getAttributes($product);
		
        if (in_array($colorAttr, $attributes)) {
			$color = @$product->getAttributeText($colorAttr);
        } else {
            if (in_array('color', $attributes)) {
                $color = @$product->getAttributeText('color');
            }
        }
        return $color;
    }

    public function getCategory($product)
    {
        $category = Mage::registry('current_category');
        $catName = '';
        if (isset($category)) {
            $catName = $category->getName();
        } else {
            $category = $product->getCategoryCollection()
                ->addAttributeToSelect('name')
                ->getFirstItem();
            if($category->getName()) $catName = $category->getName();
        }
        return $catName;
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
	$data = array();
	$customer = Mage::getSingleton('customer/session');

	// visitorId
	if ($customer->getCustomerId()) $data['visitorId'] = (string)$customer->getCustomerId();

	// visitorLoginState
	$data['visitorLoginState'] = ($customer->isLoggedIn()) ? 'Logged in' : 'Logged out';

	// visitorType
	$data['visitorType'] = (string)Mage::getModel('customer/group')->load($customer->getCustomerGroupId())->getCode();

        $data = $this->getVisitorOrderData($customer);
        	
	return $data;
    }
    
    //check if user placed orders before and get total
    private function getVisitorOrderData($customer = false)
    {
        $data = array();
        $orders = false;
        
        if(!$customer) $customer = Mage::getSingleton('customer/session');
        
        if(Mage::getSingleton('customer/session')->isLoggedIn())
        {
            $orders = Mage::getResourceModel('sales/order_collection')->addFieldToSelect('grand_total')->addFieldToFilter('customer_id',$customer->getId());
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
        if($orders)
        {
            foreach ($orders as $order)
            {
                $ordersTotal += $order->getGrandTotal();
            }
        }
        
        $data['visitorLifetimeValue'] = $this->convertCurrency($ordersTotal);
        
        return $data;
    }
    
    private function convertCurrency($price)
    {
        $from = Mage::app()->getStore()->getBaseCurrencyCode();
        $to = Mage::app()->getStore()->getCurrentCurrencyCode();
        
        if($from != $to)
        {
            $price = Mage::helper('directory')->currencyConvert($price, $from, $to);
            $price = Mage::app()->getStore()->roundPrice($price);
        }
        
        return $price;
    }
}