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
            $last_cat = Mage::helper('panalysis_tagmanager')->getLastCategoryViewed();
            
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
                        $__prod = $helper->createProductArray($ip->getId(), $my_qty);
                        if($last_cat){
                            $__prod['category'] = $last_cat;
                        }
                        $tmProduct[] = $__prod;
                    }
                }

            } elseif ($type === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                //$optionCollection = $product->getTypeInstance()->getOptionsCollection();
                //$selectionCollection =$product->getTypeInstance()->getSelectionsCollection($product->getTypeInstance()->getOptionsIds());
                //$options = $optionCollection->appendSelections($selectionCollection);
                
                $tst_sku = $product->getSku();
                if(strpos($tst_sku, '-')>0){
                    $sku_array = explode('-',$tst_sku);
                    $sku = $sku_array[0];
                }
                else{
                    $sku = $tst_sku;
                }
                
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);
                
                $__prod = $helper->createProductArray($ip->getId(), $qty);
                if($last_cat){
                    $__prod['category'] = $last_cat;
                }
                $tmProduct[] = $__prod;

            } elseif($type === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE){
                $_parent = Mage::getModel('catalog/product')->load($params['product']);
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $product->getSku());
                $__prod = $helper->createProductArray($product, $qty);
                // replace the SKU with the item from the parent SKU. Google Analytics doesn't handle SKUs properly.
                $__prod['id'] = $_parent->getSku();
                $__prod['variant'] = $product->getSku();
                if($last_cat){
                    $__prod['category'] = $last_cat;
                }
                $tmProduct[] = $__prod;
            }else {
                $__prod = $helper->createProductArray($product->getId(), $qty);
                if($last_cat){
                    $__prod['category'] = $last_cat;
                }
                $tmProduct[] = $__prod;
            }
            
            $session = Mage::getSingleton('core/session');

            if(Mage::app()->getRequest()->isXmlHttpRequest()){
                $session->setData('panalysis_tagmanager',1);
            }
            
            $session->setTmProduct($tmProduct);
            
            foreach($tmProduct as $prod)
                $helper->updateSkuCategoryDetails($prod['id'],$prod['category']);
            
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
        $session = Mage::getSingleton('core/session');
        try {
            $addProduct = array();
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $current_updated = $session->getTmProduct();
            if($current_updated) $addProduct = $current_updated;
            $addProduct[$product_id] = $helper->createProductArray($product_id, $qty);
            $session->setTmProduct($addProduct);
            
        } catch (exception $e) {
            Mage::logException($e);
        }
    }
    
    private function removeItemFromCart($product, $qty)
    {
        $helper = Mage::helper('panalysis_tagmanager');
        $session = Mage::getSingleton('core/session');
        try {
            $rmProduct = array();
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $current_updated = $session->getRmProducts();
            if($current_updated) $rmProduct = $current_updated;
            $rmProduct[$product] = $helper->createProductArray($product, $qty);
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
            // use the parent product as GA doesn't handle configurable skus well.
            return $this->removeItemFromCart($product, $item->getQty());
        } 
        elseif($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
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
        } 
        else return $this->removeItemFromCart($product->getId(), $item->getQty());
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
                    $rmProduct = $helper->createProductArray($product->getId(), $item->getQty());

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
        if(! $helper->getTrackProductList())
            return;

        $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');

        $catProducts = array();
        $catName = $tm->getCategory();
        $_collection = $observer->getEvent()->getCollection();
        $colorAttr = 'color';
        $brandAttr = 'manufacturer';
            
        if($helper->getBrandCode() != "")
            $brandAttr = $helper->getBrandCode();
        
        $_collection->addAttributeToSelect($colorAttr)->addAttributeToSelect($brandAttr);
        $_products = $_collection->load();
        $limit = $helper->getListMaxProducts();

        if(Mage::app()->getRequest()->getModuleName() == 'catalogsearch'){
            $view = 'Search Results';
            $catName = "Search Results";
        } 
        else $view = 'Category Listing';
        
        $i = 0;
        foreach ($_products as $_product)
        {
            if($i >= $limit) break;

            $brand = $tm->getBrand($_product);

            $prod = array(
                'name' => $_product->getName(),
                'id' => $_product->getSku(),
                'list' => $view,
                'position' => ++$i,
                'category' => $catName,
                'url' => $_product->getProductUrl()
            );
            
            if($_product->getPrice() > 0 )
                $prod['price'] = $_product->getPrice();
            elseif($_product->getMinPrice() > 0)
                $prod['price'] = $_product->getMinPrice();

            if($helper->getUseMultipleCurrencies())
                $prod['price'] = Mage::helper('core')->currency($prod['price'], false, false);

            $prod['price'] = number_format($prod['price'], 2);

            if($variant) $prod['variant'] = $variant;
            if($brand) $prod['brand'] = $brand;

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