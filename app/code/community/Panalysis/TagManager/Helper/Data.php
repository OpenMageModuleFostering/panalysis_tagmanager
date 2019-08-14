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
    const USE_MULTIPLE_CURRENCIES = 'panalysis_tagmanager/gtm/multiple_currencies';
    const GTM_BRAND_CODE = 'panalysis_tagmanager/gtm/brandcode';
    const AJAX_ENABLED = 'panalysis_tagmanager/gtm/enable_ajax';
    const OPTIMIZELY_ENABLED = 'panalysis_tagmanager/optimizely/is_enabled';
    const OPTIMIZELY_ID = 'panalysis_tagmanager/optimizely/optimizelyid';
    
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
    
    public function getUseMultipleCurrencies()
    {
        return Mage::getStoreConfig(self::USE_MULTIPLE_CURRENCIES, $this->store_id);
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
    
    public function useOptimizely()
    {
        return Mage::getStoreConfig(self::OPTIMIZELY_ENABLED, $this->store_id);
    }
    
    public function getOptimizelyId()
    {
        return Mage::getStoreConfig(self::OPTIMIZELY_ID, $this->store_id);
    }

    public function getCurrencyCode()
    {
        if($this->getUseMultipleCurrencies())
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        else
            $currencyCode = Mage::app()->getStore()->getBaseCurrencyCode();

        return $currencyCode;
    }
    
    public function createProductArray($product_obj, $qty = 1)
    {
        $product = $this->getProductFromObj($product_obj);
        
        if($product)
        {
            $tm = Mage::getSingleton('panalysis_tagmanager/tagmanager');
            $final_price = $tm->getPrice($product->getId());
            
            $product_array = array(
                'name' => $product->getName(),
                'id' => $product->getSku(),
                'variant' => $product->getSku(),
                'price' => $final_price,
                'category' => $tm->getCategory($product)
            );
           
            if($brand = $tm->getBrand($product)) $product_array['brand'] = $brand;
            
            if($qty !== false && (int)$qty) $product_array['quantity'] = (int)$qty;
            
            return $product_array;
            
        } else return array();
    }
    
    private function getProductFromObj($product_obj){
        $type = gettype($product_obj);
        if($type == 'string' || $type == 'integer' ){
            return Mage::getModel('catalog/product')->load($product_obj);
        }
        elseif($type == 'object') {
            $class = get_class($product_obj);
            if($class == 'Mage_Catalog_Model_Product'){
                return $product_obj;
            }
            else{
                return;
            }
        }
        else { 
            return;
            
        }
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
                'currencyCode' => $this->getCurrencyCode(),
                'detail' => array(
                        'products' => $products
                )
            )
        );
        return $data;
    }
    
    public function buildOrderData($order){    
        
        
        // get the add to cart category from the cache
        foreach($order['transactionProducts'] as $k => $p){
            $cat = $this->getSkuCategory($p['id']);
            if($cat)
                $order['transactionProducts'][$k]['category'] = $cat;
        }
        
        $data = array(
            'ecommerce' => array(
                'currencyCode' => $this->getCurrencyCode(),
                'purchase' => array(
                    'actionField' => $order['actionField'],
                    'products' => $order['transactionProducts']
                )
            )
        );
        $this->expireSkuCategory();
        return $data;

    }

    public function buildCategoryData($prodlist){
        $data = array();
        if(count($prodlist)>0){
            
            $data = array(
                'event' => 'productlist',
                'ecommerce' => array(
                    'currencyCode' => $this->getCurrencyCode(),
                    'impressions' => $prodlist
                )
            );
        }
            
        return $data;
    }
    
    public function buildCheckoutData($products){
        if(empty($products)) return array();
        foreach($products as $k=>$v){
            if($cat = $this->getSkuCategory($v['id'])){
                $products[$k]['category'] = $cat;
            }
        }
        
        $data = array(
            'event' => 'checkout',
            'ecommerce' => array(
                'currencyCode' => $this->getCurrencyCode(),
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
        if(empty($products)) return array();
        foreach($products as $k=>$v){
            if($cat = $this->getSkuCategory($v['id'])){
                $products[$k]['category'] = $cat;
            }
        }
        
        $data = array(
            'event' => 'checkout',
            'ecommerce' => array(
                'currencyCode' => $currencyCode = $this->getCurrencyCode(),
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
    
    public function buildAddToCartData($products)
    {
        foreach($products as $k=>$v){
            if($cat = $this->getSkuCategory($v['id'])){
                $products[$k]['category'] = $cat;
            }
        }
        
        $data = array(
            'event' => 'addToCart',
            'ecommerce' => array(
                'currencyCode' => $this->getCurrencyCode(),
                'add' => array(
                    'products' => array_values($products)
                )
            )
        );
    
        return $data;
    }
    
    public function AjaxEnabled()
    {
        return Mage::getStoreConfig(self::AJAX_ENABLED, $this->store_id);
    }
    
    public function getLastCategoryViewed(){
        return Mage::getSingleton('core/session')->getGTMLastCategory();
    }
    
    public function setLastCategoryViewed($cat){
        Mage::getSingleton('core/session')->setGTMLastCategory($cat);
    }
    
    public function updateSkuCategoryDetails($sku,$cat){

        $session = Mage::getSingleton('core/session');
        
        $skuCats = $session->getGTMCartSkuCats();
        if(! $skuCats){
            $skuCats = array();
        }
        $skuCats[$sku] = $cat;
        
        $session->setGTMCartSkuCats($skuCats);
    }
    
    public function getSkuCategory($sku){
        $skuCats = Mage::getSingleton('core/session')->getGTMCartSkuCats();
        if(array_key_exists($sku,$skuCats))
            return $skuCats[$sku];
        else 
            return "";
    }
    
    public function expireSkuCategory(){
        Mage::getSingleton('core/session')->unsGTMCartSkuCats();
    }
}