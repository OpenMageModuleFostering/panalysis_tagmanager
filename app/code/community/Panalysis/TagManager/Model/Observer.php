<?php

class Panalysis_TagManager_Model_Observer
{	
    public function getLayout() 
    { 
        return Mage::getSingleton('core/layout'); 
    }
     
    public function checkoutCartAddProductComplete($observer)
    {
	$helper = Mage::helper('panalysis_tagmanager');
        try {
            $product = $observer->getProduct();
            $params = $observer->getRequest()->getParams();
            $qty = 1;
            $price = '';
            $tmProduct = array();
            
            if (isset($params['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $qty = $filter->filter($params['qty']);
            }

            $type = $product->getTypeID();

            if ($type === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            	$associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
            	foreach($associatedProducts as $ip)
            	{
                    $my_qty = $params['super_group'][$ip->getId()];
                    if($my_qty > 0){
                        $tmProduct[] = $helper->CreateProductArray($ip->getId(), $my_qty);
                    }
            	}

            } elseif ($type === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            	$optionCollection = $product->getTypeInstance()->getOptionsCollection();
            	$selectionCollection =$product->getTypeInstance()->getSelectionsCollection($product->getTypeInstance()->getOptionsIds());
		$options = $optionCollection->appendSelections($selectionCollection);
		foreach( $options as $option )
		{
                    $_selections = $option->getSelections();	
                    
                    foreach( $_selections as $selection )
                    { 
			$tmProduct[] = $helper->CreateProductArray($selection->getId(), $qty);
                    }
		} 
            } elseif($type === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE){
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $product->getSku());
                $tmProduct[] = $helper->CreateProductArray($product->getId(), $qty);
            }else {
                $tmProduct[] = $helper->CreateProductArray($product->getId(), $qty);
            }
            
            $session = Mage::getSingleton('core/session');

            if(Mage::app()->getRequest()->isXmlHttpRequest()){
                $session->setData('panalysis_tagmanager',1);
            }
            
            $session->setTmProduct($tmProduct);
            
        } catch (exception $e) {
            Mage::logException($e);
        }
    }
      
    public function hookToUpdateToCart($event)
    {        
        $new_qty = Mage::app()->getRequest()->getParam('cart');
        $updated_cart = array();
        foreach($new_qty as $key => $item)
        {
            $updated_cart[$key] = (int)$item['qty'];
        }
     
        $cart = Mage::getModel('checkout/cart')->getQuote();
        $cartItems = $cart->getAllItems();

        foreach ($cartItems as $item)
        {
            if (array_key_exists($item->getId(), $updated_cart) || $item->getParentItemId())
            {
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                if($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE ||
                    $product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
                continue;

                if($item->getParentItemId())
                {
                    $parent = Mage::getModel('sales/quote_item')->load($item->getParentItemId());
                    $new_qty = $updated_cart[$item->getParentItemId()];
                    $old_qty = (int)$parent->getQty();
                }else{
                    $new_qty = $updated_cart[$item->getId()];
                    $old_qty = $item->getQty();
                }
                
                if($old_qty > $new_qty) //item removed from cart
                {
                    $new_qty = $old_qty - $new_qty;
                    $this->removeItemFromCart($product->getId(), $new_qty);
                }elseif($old_qty < $new_qty) //item added to cart (qty increment)
                {
                    $new_qty = $new_qty - $old_qty;
                    $this->addItemToCart($product->getId(), $new_qty);
                }
            }
        }
        return $event;
    }

    private function addItemToCart($product_id, $qty=1)
    {
        $helper = Mage::helper('panalysis_tagmanager');
        try {
            $addProduct = array();
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $current_updated = Mage::getSingleton('core/session')->getTmProduct();
            if($current_updated) $addProduct[] = $current_updated;
            $addProduct[] = $helper->CreateProductArray($product_id, $qty);
            $session = Mage::getSingleton('core/session');
            $session->setTmProduct($addProduct);
        } catch (exception $e) {
            Mage::logException($e);
        }
    }
    
    private function removeItemFromCart($product_id, $qty)
    {
        $helper = Mage::helper('panalysis_tagmanager');
        try {
            $rmProduct = array();
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $current_updated = Mage::getSingleton('core/session')->getRmProducts();
            if($current_updated) $rmProduct[] = $current_updated;
            $rmProduct[] = $helper->CreateProductArray($product_id, $qty);
            $session = Mage::getSingleton('core/session');
            $session->setRmProducts($rmProduct);
        } catch (exception $e) {
            Mage::logException($e);
        }
    }
    
    public function salesQuoteRemoveItem($observer)
    {
        $item = $observer->getQuoteItem()->getProduct();

        $product = Mage::getModel('catalog/product')->load($item->getId());
        if($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        {
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());
            return $this->removeItemFromCart($product->getId(), $item->getQty());
        }elseif($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        {
            $qty = $item->getQty();
            $quote_id = $observer->getQuoteItem()->getData('quote_id');
            $quote_item_id = $observer->getQuoteItem()->getData('item_id');
            
            $parent_quote = Mage::getModel("sales/quote")->load($quote_id);
          
            $aChildQuoteItems = Mage::getModel("sales/quote_item")
                                ->getCollection()
                                ->setQuote($parent_quote)
                                ->addFieldToFilter("parent_item_id", $quote_item_id);
            
            foreach ($aChildQuoteItems->getItems() as $child)
            {
                $this->removeItemFromCart($child->getProductId(), $item->getQty());
            }
            
            return true;
        }else return $this->removeItemFromCart($product->getId(), $item->getQty());
    }

    public function checkoutCartEmpty()
    {
	$helper = Mage::helper('panalysis_tagmanager');
        $post = Mage::app()->getRequest()->getPost('update_cart_action');
        if ($post == 'empty_cart') {
            $rmProducts = array();
            try {
                $quote = Mage::helper('checkout/cart')->getQuote(); //quote
                $allQuoteItems = $quote->getAllItems(); // quote items
                foreach ($allQuoteItems as $item) {
                    $product = Mage::getSingleton('catalog/product')->load($item->getProductId());
                    $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
                    $rmProduct = $helper->CreateProductArray($product->getId(), $item->getQty());

                    $rmProducts[] = $rmProduct;
                }
                $session = Mage::getSingleton('core/session');
                $session->setRmProducts($rmProducts);

            } catch (exception $e) {
                Mage::logException($e);
            }
        }
    }
    
    public function categoryView(Varien_Event_Observer $observer)
    {  	
        $helper = Mage::helper('panalysis_tagmanager');
        $catProducts = array();
        $_products = $observer->getEvent()->getCollection()->load();
		
        $limit = $helper->getListMaxProducts();

        if(Mage::app()->getRequest()->getModuleName() == 'catalogsearch') $view = 'Search Results';
        else $view = 'category';
        
        $i = 0;
        foreach ($_products as $_product)
	{
            if($i >= $limit) break;
            
            $prod = $helper->CreateProductArray($_product->getId());
            $prod['list'] = $view;
            $prod['position'] = ++$i;
            $catProducts[] = $prod;
        }
		
		$dataLayer = $helper->buildCategoryData($catProducts);
        $dataLayerJs = "<script type='text/javascript'>var dataLayer = dataLayer || []; dataLayer.push(" . json_encode($dataLayer,JSON_PRETTY_PRINT) .");</script>";
        
        echo $dataLayerJs;
        
    }
    
    public function startCheckout($observer)
    {
        $myDataLayer = Mage::app()->getLayout()->createBlock('panalysis_tagmanager/tagmanager');
        $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
        $tm->setCheckoutState("start");
    	Mage::dispatchEvent('panalysis_start_checkout');
    }
} 