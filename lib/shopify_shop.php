<?php

class ShopifyShop extends ShopifyResource {

    protected static $resource_singular = true;

    static function shop(Shopify $api) {
        $data = $api->call('shop');
        
        return new self($api, $data['shop']);
    }
    
}
