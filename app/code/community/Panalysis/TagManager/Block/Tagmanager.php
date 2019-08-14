<?php

class Panalysis_TagManager_Block_Tagmanager extends Mage_Core_Block_Template
{
    public function buildDataLayer()
    {
    
        $helper = Mage::helper('panalysis_tagmanager');
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
            $cl = Mage::getModel('catalog/layer');
            $level = $cl->getCurrentCategory()->getParentCategory()->getLevel();
            $cat_str = $cl->getCurrentCategory()->getName();
            if($level > 1){
                $cat = $cl->getCurrentCategory()->getParentCategory();
                while($level > 1){
                    $cat_str = $cat->getName() . "/" . $cat_str;
                    $cat = $cat->getParentCategory();
                    $level--;
                }
            }
            $dataLayer['productCategory'] = $cat_str;
        }

        if ($this->getPage() == 'Product Detail') $dataLayer += $helper->buildProductDetailsData($this->getProductDetails());
        if ($this->getPage() == 'Order Success') $dataLayer += $helper->buildOrderData($this->getOrderData());
        if ($this->getPage() == 'Shopping Cart') $dataLayer += $helper->buildCheckoutData($this->getCartProducts());
        if ($this->getPage() == 'Onepage Checkout') $dataLayer += $helper->buildOnePageCartCheckoutData($this->getCartProducts());
        if ($this->getPage() == '404') $dataLayer['event']='404';

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
        
        Mage::log("Panalysis - " . $page, null, 'events.log', true);
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
                    $products[] = $helper->CreateProductArray($child);
                }
        } elseif($productType === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
                $associatedProducts = $_product->getTypeInstance(true)->getAssociatedProducts($_product);
                $i = 0;
                foreach($associatedProducts as $option) 
                {
                    $products[$i] = $helper->CreateProductArray($option->getId(), $option->getQty());
                    $products[$i]['id'] = $option->getSku();
                    $products[$i]['name'] = $option->getName();
                    ++$i;
                }

        } else {
                $products[] = $helper->CreateProductArray($_product->getId());
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
        $data = array();

        try {
            if ($order->getId()) {
                
                $products = array();

                // convert the amounts to the correct currency based on the user's selected currency
                $revenue = Mage::helper('core')->currency($order->getBaseGrandTotal(), false, false);
                $tax = Mage::helper('core')->currency($order->getBaseTaxAmount(), false, false);
                $shipping = Mage::helper('core')->currency($order->getBaseShippingAmount(), false, false);

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
                        
                foreach ($order->getAllItems() as $item)
                {
                           
                            $product = Mage::getModel('catalog/product')->load($item->getProductId());
                            $product_type = $product->getTypeId();
                            
                            if ($product_type === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                                $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());                   
                
                            if ($product_type === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) continue;

                            if (empty($products[$item->getSku()])) {
                                $products[$item->getSku()] = $helper->CreateProductArray($product->getId(), $item->getQtyOrdered(), true);
                            }elseif((int)$item->getPrice()) {
                                $products[$item->getSku()]['quantity'] += (int)$item->getQtyOrdered();
                            }
                            
                }

                        foreach ($products as $product)
                        {
                            $data['transactionProducts'][] = $product;
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
            $cart = Mage::getModel("checkout/cart");
            $cartItems = $cart->getItems();
            if(count($cartItems) ==0) return;
            
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $cartProducts = array();

            foreach ($cartItems as $item)
            {
                //$itemId = $item->getId();
                $productId = $item->getProductId();
                $product = Mage::getSingleton('catalog/product')->load($productId);
                if($item->getProductType() != 'configurable' && $item->getProductType() != 'bundle')
                {
                    $myItem = $helper->CreateProductArray($product->getId(), $item->getQty());

                    if($item->getParentItemId())
                    {
                        $parent = Mage::getModel('sales/quote_item')->load($item->getParentItemId());
                        $myItem['quantity'] = (int)$parent->getQty();
                    }

                    array_push($cartProducts,$myItem);
                }
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