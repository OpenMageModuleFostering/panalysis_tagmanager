<?php

class Panalysis_TagManager_Helper_Data extends Mage_Core_Helper_Abstract
{
    const GTM_CONTAINER_ID = 'panalysis_tagmanager/gtm/containerid';
    const GTM_TRACK_PRODUCT_LISTS = 'panalysis_tagmanager/gtm/enable_product_lists';
    const OG_IS_ENABLED = 'panalysis_tagmanager/opengraph/is_enabled';
    const FB_APP_ID = 'panalysis_tagmanager/opengraph/facebookappid';
    const FB_ADMIN_ID = 'panalysis_tagmanager/opengraph/facebookadminid';
    const PINTEREST_ID = 'panalysis_tagmanager/opengraph/pinterestid';
    const TWITTER_ENABLED = 'panalysis_tagmanager/opengraph/use_twittercards';
    const TWITTER_USELARGEIMAGE = 'panalysis_tagmanager/opengraph/use_largeimage';
    const TWITTER_STORE_USERNAME = 'panalysis_tagmanager/opengraph/twitterstoreid';
    const TWITTER_CREATOR_USERNAME = 'panalysis_tagmanager/opengraph/twittercreatorid';
    const TWITTER_IMAGE = 'panalysis_tagmanager/opengraph/twitterimage';
    const GTM_MAX_PRODUCTS_IN_LISTS = 'panalysis_tagmanager/gtm/max_products';
    const GTM_BRAND_CODE = 'panalysis_tagmanager/gtm/brandcode';
    const GTM_COLOR_CODE = 'panalysis_tagmanager/gtm/colorcode';
    const AJAX_ENABLED = 'panalysis_tagmanager/gtm/enable_ajax';
    
    private $store_id = 0;
    
    function __construct() {
        $this->store_id = Mage::app()->getStore()->getStoreId();
    }
    
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getNode()->modules->Panalysis_TagManager->version;
    }
    
    public function getContainerId()
    {
        return Mage::getStoreConfig(self::GTM_CONTAINER_ID, $this->store_id);
    }
    
    public function getTrackProductList()
    {
        return Mage::getStoreConfig(self::GTM_TRACK_PRODUCT_LISTS, $this->store_id);
    }
    
    public function useOpenGraph()
    {
        return Mage::getStoreConfig(self::OG_IS_ENABLED, $this->store_id);
    }
    
    public function getFacebookAppId()
    {
        return Mage::getStoreConfig(self::FB_APP_ID, $this->store_id);
    }
    
    public function getFacebookAdminId()
    {
        return Mage::getStoreConfig(self::FB_ADMIN_ID, $this->store_id);
    }
    
    public function getPinterestId()
    {
        return Mage::getStoreConfig(self::PINTEREST_ID, $this->store_id);
    }
    
    public function useTwitterCards()
    {
        return Mage::getStoreConfig(self::TWITTER_ENABLED, $this->store_id);
    }
    
    public function useTwitterLageImage()
    {
        return Mage::getStoreConfig(self::TWITTER_USELARGEIMAGE, $this->store_id);
    }
    
    public function getTwitterStoreUsername()
    {
        return Mage::getStoreConfig(self::TWITTER_STORE_USERNAME, $this->store_id);
    }
    
    public function getTwitterCreatorUsername()
    {
        return Mage::getStoreConfig(self::TWITTER_CREATOR_USERNAME, $this->store_id);
    }
    
    public function getTwitterImage()
    {
        return Mage::getStoreConfig(self::TWITTER_IMAGE, $this->store_id);
    }
    
    public function getListMaxProducts()
    {
        return (int)Mage::getStoreConfig(self::GTM_MAX_PRODUCTS_IN_LISTS, $this->store_id);
    }
    
    public function getBrandCode()
    {
        return Mage::getStoreConfig(self::GTM_BRAND_CODE, $this->store_id);
    }
   
    public function getColorCode()
    {
        return Mage::getStoreConfig(self::GTM_COLOR_CODE, $this->store_id);
    }
    
    public function CreateProductArray($product_id, $qty = 1, $full_category = false)
    {
        $product = Mage::getModel('catalog/product')->load($product_id);
        if($product)
        {
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $final_price = $tm->getPrice($product->getId());
            
            $product_array = array(
                'name' => $product->getName(),
                'id' => $product->getSku(),
                'price' => $final_price,
            );
           
            if($brand = $tm->getBrand($product)) $product_array['brand'] = $brand;
            if($variant = $tm->getVariant($product)) $product_array['variant'] = $variant;

            if($full_category) $product_array['category'] = $tm->getCatArray($product);
            elseif($current_category = Mage::registry('current_category')) $product_array['category'] = $current_category->getName();
            else $product_array['category'] = $tm->getCategory($product);
            
            if($qty !== false && (int)$qty) $product_array['quantity'] = (int)$qty;
            
            return $product_array;
            
        } else return array();
    }
    
    //get products just once
    public function getBundleProducts($product_id)
    {
        $_product = Mage::getModel('catalog/product')->load($product_id);
        if ($_product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        {
            $associated = $_product->getTypeInstance(true)->getChildrenIds($_product->getId(), false);
            $associated_ids = array();
            foreach($associated as $group)
            {
                foreach($group as $child)
                {
                    if (!in_array($child, $associated_ids))
                    {
                        $associated_ids[] = $child;
                    }
                }
            }
            
            return $associated_ids;
        }   
        
        return array();
    }
    
    public function buildProductDetailsData($products){
        $data = array(
            'ecommerce' => array(
                'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'detail' => array(
                        'products' => $products
                )
            )
        );
        return $data;
    }
    
    public function buildOrderData($order){      
        $data = array(
            'ecommerce' => array(
                'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'purchase' => array(
                    'actionField' => $order['actionField'],
                    'products' => $order['transactionProducts']
            )
            )
        );
        
        return $data;

    }

    public function buildCategoryData($prodlist){
        $data = array();
        if(count($prodlist)>0){
            
            $data = array(
                'event' => 'productlist',
                'ecommerce' => array(
                    'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                    'impressions' => $prodlist
                )
            );
        }
            
        return $data;
    }
    
    public function buildCheckoutData($products){
        if(empty($products)) return array();
        $data = array(
            'event' => 'checkout',
            'ecommerce' => array(
                'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'checkout' => array(
                        'actionField' => array(
                            'step' =>'1', 
                            'option' => 'review cart'
                        ),
                        'products' => $products
                    )
                )
        );
        
        return $data;
    }
    
    public function buildOnePageCartCheckoutData($products){
        $data = array(
            'event' => 'checkout',
            'ecommerce' => array(
                'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'checkout' => array(
                    'actionField' => array(
                        'step' =>'1', 
                        'option' => 'start checkout'
                    ),
                    'products' => $products
                )
            )
        );
        
        return $data;
    }
    
    public function buildAddToCartData($prods)
    {
        $data = array(
            'event' => 'addToCart',
            'ecommerce' => array(
                'currencyCode' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'add' => array(
                            'products' => array_values($prods)
                )
            )
        );
    
        return $data;
    }
    
    public function AjaxEnabled()
    {
        return Mage::getStoreConfig(self::AJAX_ENABLED, $this->store_id);
    }
    
}