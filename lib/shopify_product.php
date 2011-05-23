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
    
    static function count($api) {
        $data = $api->call('products/count');
        return $data['count'];
    }
    
}
