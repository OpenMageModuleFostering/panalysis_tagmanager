<?php

class Panalysis_TagManager_Block_Tagmanager extends Mage_Core_Block_Template
{
    public function buildDataLayer()
    {
    
        $helper = Mage::helper('panalysis_tagmanager');
        $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
        $session = Mage::getSingleton('core/session');
        $trackProductLists = $helper->getTrackProductList();
        
        // to store the main data layer values
        $dataLayer = array();
        // to store other secondary events that can occur once the page has loaded such as add to cart, remove from cart
        $additionalEventsAdd = array();
        $additionalEventsRemove = array();

        $visitorState = $this->getVisitorData();
        $dataLayer += $visitorState;
        $dataLayer['pageType'] = $this->getRequest()->getModuleName() . "-" . $this->getRequest()->getControllerName();
        if($this->getPage() == 'Product Category'){
            $__cat =  $tm->getCategory();
            $dataLayer['productCategory'] = $__cat;
            $helper->setLastCategoryViewed($__cat);
        }
        elseif ($this->getPage() == 'Product Detail'){ 
            $__cat =  $tm->getCategory();
            $helper->setLastCategoryViewed($__cat);
            $dataLayer += $helper->buildProductDetailsData($this->getProductDetails());
        }
        elseif ($this->getPage() == 'Order Success'){ 
            $dataLayer += $helper->buildOrderData($this->getOrderData());
        }
        elseif ($this->getPage() == 'Shopping Cart'){
            $dataLayer += $helper->buildCheckoutData($this->getCartProducts());
        }
        elseif ($this->getPage() == 'Onepage Checkout'){
            $dataLayer += $helper->buildOnePageCartCheckoutData($this->getCartProducts());
        }
        elseif ($this->getPage() == '404'){ 
            $dataLayer['event']='404';
        }

        $dataLayerJs = "dataLayer.push(" . json_encode($dataLayer,JSON_PRETTY_PRINT) .");\n";

        // if this is not an order completion page then check for any additional add to cart or remove from cart events.
        if ($this->getPage() != 'Order Success') {
            // Add to Cart Events
            $tmProduct = $session->getTmProduct();
            if($tmProduct) $additionalEventsAdd = $helper->buildAddToCartData($tmProduct); // then there is an add to cart product
            $session->unsTmProduct();

            // Remove from Cart Events
            $rmProducts = $session->getRmProducts();
            if ($rmProducts) $additionalEventsRemove = $this->buildRemoveFromCartData($rmProducts); 
            $session->unsRmProducts();
            if($additionalEventsAdd) $dataLayerJs .=  "dataLayer.push(" . json_encode($additionalEventsAdd,JSON_PRETTY_PRINT) .");\n";
            if($additionalEventsRemove) $dataLayerJs .=  "dataLayer.push(" . json_encode($additionalEventsRemove,JSON_PRETTY_PRINT) .");\n";
        }

    
        return $dataLayerJs;
    }
    

    public function getTwitterDetails()
    {
       $helper = Mage::helper('panalysis_tagmanager');
        $twitterData = [];
        $twitterData['store_username'] = $helper->getTwitterStoreUsername();
        if($helper->getTwitterCreatorUsername() != "")
        {
            $twitterData['creator_username'] = $helper->getTwitterCreatorUsername();
        }
        else
        {
            $twitterData['creator_username'] = $twitterData['store_username'];
        }
        
        if($helper->useTwitterLageImage()){
                $twitterData['card_format'] = 'summary_large_image';
        }else{
                $twitterData['card_format'] = 'summary';
        }
        
        if($this->getPage() == "Product Detail"){
            $twitterData['image'] = Mage::registry('product')->getImageUrl();
        }
        else{
            $twitterData['image']  = $helper->getTwitterImage();
        }
        
        return $twitterData;    
    }

    public function getPage()
    {
        $page = '';
        if ($this->getRequest()->getModuleName() == 'catalog'
            && $this->getRequest()->getControllerName() == 'product'
            && Mage::registry('current_product')
        ) {
            $page = 'Product Detail';
        }
        if ($this->getRequest()->getModuleName() == 'checkout'
            && $this->getRequest()->getControllerName() == 'onepage'
            && $this->getRequest()->getActionName() == 'success'
        ) {
            $page = 'Order Success';
        }
        if ($this->getRequest()->getModuleName() == 'checkout'
            && $this->getRequest()->getControllerName() == 'onepage'
            && $this->getRequest()->getActionName() == 'index'
        ) {
            $page = 'Onepage Checkout';
        }
        if ($this->getRequest()->getModuleName() == 'checkout'
            && $this->getRequest()->getControllerName() == 'cart'
            && $this->getRequest()->getActionName() == 'index'
        ) {
            $page = 'Shopping Cart';
        }
        if ($this->getRequest()->getModuleName() == 'catalog'
            && $this->getRequest()->getControllerName() == 'category'
            && $this->getRequest()->getActionName() == 'view'
        ) {
            $page = 'Product Category';
        }
        if (Mage::app()->getRequest()->getActionName() == 'noRoute'){
            $page = '404';
        }
        
        return $page;
    }

    public function getProductDetails()
    {
        $helper = Mage::helper('panalysis_tagmanager');
        $_product = Mage::registry('current_product');
        $tm = Mage::getModel('panalysis_tagmanager/tagmanager');
        $products = array();
        $productType = $_product->getTypeId();
        if ($productType === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
            {
                $associated_ids = $helper->getBundleProducts($_product->getId());
                foreach($associated_ids as $child)
                {
                    $products[] = $helper->createProductArray($child);
                }
        } elseif($productType === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
                $associatedProducts = $_product->getTypeInstance(true)->getAssociatedProducts($_product);
                $i = 0;
                foreach($associatedProducts as $option) 
                {
                    $products[$i] = $helper->createProductArray($option->getId(), $option->getQty());
                    $products[$i]['id'] = $option->getSku();
                    $products[$i]['name'] = $option->getName();
                    ++$i;
                }

        } else {
                $products[] = $helper->createProductArray($_product);
        }
                
        return $products;
    }
    
    public function getOrderData()
    {
        
        $tm = Mage::getModel('panalysis_tagmanager/tagmanager');
        $helper = Mage::helper('panalysis_tagmanager');
        $order = Mage::getSingleton('sales/order');
        $order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
        $storeName = Mage::app()->getStore()->getName();
        $collection = Mage::getModel('catalog/product')->getCollection();
        $data = array();

        try {
            if ($order->getId()) {
                
                $products = array();

                // convert the amounts to the correct currency based on the user's selected currency
                if($helper->getUseMultipleCurrencies())
                {
                    $revenue = Mage::helper('core')->currency($order->getGrandTotal(), false, false);
                    $tax = Mage::helper('core')->currency($order->getTaxAmount(), false, false);
                    $shipping = Mage::helper('core')->currency($order->getShippingAmount(), false, false);
                }
                else
                {
                    $revenue = $order->getBaseGrandTotal();
                    $tax = $order->getBaseTaxAmount();
                    $shipping = $order->getBaseShippingAmount();
                }
                $revenue = round($revenue,2);
                $tax = round($tax,2);
                $shipping = round($shipping,2);

                $data = array(
                            'actionField' => array(
                                'id' => $order->getIncrementId(),
                                'affiliation' => $storeName,
                                'revenue' => (float) $revenue,
                                'tax' => (float) $tax, 
                                'shipping' => (float) $shipping,
                                'coupon' => ($order->getCouponCode() ? $order->getCouponCode() : ''),
                            ),
                            'products' => array()
                );
                
                $filterIds = array();
                $items = array();
                foreach ($order->getAllVisibleItems() as $item)
                {
                    if($parent = $item->getParentItem()){
                        $filterIds[] = $parent->getProductId();
                        $items[$parent->getProductId()] = array(
                            'obj' => $parent,
                            'variant' => $item->getSku()
                        );
                        $items[$parent->getProductId()]['variant'] = $parent;
                    } else {
                        $filterIds[] = $item->getProductId();
                        $items[$item->getProductId()] = array(
                            'obj' => $item,
                            'variant' => $item->getSku()
                        );
                        
                    }
                }
                
                $productcollection = $collection->addAttributeToSelect('*')
                        ->addAttributeToFilter('entity_id', array('in' => $filterIds));
                
                foreach ($productcollection as $product) {
                
                    //$product = Mage::getModel('catalog/product')->load($item->getProductId());
                    $sku = $product->getSku();
                    
                    $item = $items[$product->getId()]['obj'];
                    $item_price = 0;
                    if($helper->getUseMultipleCurrencies()) 
                        $item_price = Mage::helper('core')->currency($item->getPrice(), false, false);
                    else
                        $item_price = $item->getBasePrice();
                    
                    $product_array = array(
                        'name' => $product->getName(),
                        'id' => $sku,
                        'variant' => $items[$product->getId()]['variant'],
                        'price' => number_format($item_price, 2),
                        'quantity' => (int)$item->getQtyOrdered(),
                        'category' => $helper->getSkuCategory($sku)
                    );

                    if($brand = $tm->getBrand($product)) $product_array['brand'] = $brand;
                    
                    $data['transactionProducts'][] = $product_array;                  
                }
            }
        } catch (exception $e) {
            Mage::logException($e);
    }
        
        return $data;

    }
    
    public function getCheckoutUrl()
    {
        return $this->getUrl('checkout/onepage', array('_secure'=>true));
    }
        
    public function buildRemoveFromCartData($prods){
        $data = array(
            'event' => 'removeFromCart',
            'ecommerce' => array(
                'remove' => array(
                    'products' =>  array_values($prods)
                )
            )
        );
        
        return $data;
    }
    
    public function getCartProducts(){
        try
        {
            $helper = Mage::helper('panalysis_tagmanager');
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $collection = Mage::getModel('catalog/product')->getCollection();
            $cartItems = $quote->getAllVisibleItems();
            if(count($cartItems) ==0) return;
            
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $cartProducts = array();
            $items = array();
            $filterList = array();
            
            foreach ($cartItems as $item)
            {
                $productId = $item->getProductId();
                
                if($parentId = $item->getParentItemId()){
                    $parent = Mage::getModel('sales/quote_item')->load($parentId);
                    $items[$parentId] = array(
                        'qty' => (int)$parent->getQty(),
                        'variant' => $item->getSku()
                        );
                    $filterList[] = $parentId;
                }
                else{
                    
                    $items[$productId] = array(
                        'qty' => (int)$item->getQty(),
                        'variant' => $item->getSku()
                        );
                    $filterList[] = $productId;
                }
            }
            
            $products = $collection->addAttributeToSelect('*') 
                ->addAttributeToFilter('entity_id', array('in' => $filterList));
            
            foreach($products as $product)
            {
               $myItem = $helper->createProductArray($product, $items[$product->getId()]['qty']);
               $myItem['variant'] = $items[$product->getId()]['variant'];
               array_push($cartProducts,$myItem);                  
            }
            return $cartProducts;
        }
        catch(exception $e)
        {
            Mage::logException($e);
        }
    }

    public function getCheckoutState(){
        $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
        return $tm->getCheckoutState();
    }
    
    public function getCategoryProducts(){
        $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
        return $tm->getCategoryProducts();
    }
    
    public function getVisitorData(){
        $tm = Mage::getModel('panalysis_tagmanager/tagmanager');
        return $tm->getVisitorData();
    }
}