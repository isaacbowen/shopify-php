<?php

class ShopifyProduct extends ShopifyResource {
    
    public $variants = array();
    protected $deleted = false;
    
    function __construct($api, $data = array(), $loaded = false) {
        parent::__construct($api, $data, $loaded);
        
        if(!empty($this->data['variants'])) {
            foreach($this->data['variants'] as $variant_data) {
                $this->variants[] = new ShopifyProductVariant($api, $variant_data, true);
            }
        }
    }
    
    function save() {
        $params = array('product' => $this->newData);
        
        if(!empty($this['id'])) {
            $data = $this->api->call('products/' . $this['id'], $params, 'put');
            $this->load($data['product']);
        } else {
            $data = $this->api->call('products', $params, 'post');
            $this->load($data['product']);
        }
        
        return true;
    }
    
    function delete() {
        if(empty($this['id']) || $this->deleted) return false;
        
        $this->api->call('products/' . $this['id'], array(), 'delete');
        $this->deleted = true;
        return true;
    }
    
    static function product($api, $id) {
        $data = $api->call("products/$id");
        return new self($api, $data['product'], true);
    }
    
    static function products($api, $params = array()) {
        $data = $api->call('products', $params);
        
        $products = array();
        foreach($data['products'] as $product_data) {
            $products[] = new self($api, $product_data, true);
        }
        return $products;
    }
    
    static function count($api) {
        $data = $api->call('products/count');
        return $data['count'];
    }
    
}