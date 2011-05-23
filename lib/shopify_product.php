<?php

class ShopifyProduct extends ShopifyResource {
    
    // populated with instances of ShopifyVariant during __construct
    public $variants = array();

    protected $resource_methods = array('save', 'delete');
    
    function __construct($api, $data = array(), $loaded = false) {
        parent::__construct($api, $data, $loaded);
        
        if(!empty($this->data['variants'])) {
            foreach($this->data['variants'] as $variant_data) {
                $this->variants[] = new ShopifyVariant($api, $variant_data, true);
            }
        }
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
