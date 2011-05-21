<?php

class ShopifyShop extends ShopifyResource {

    static function shop(Shopify $api) {
        $data = $api->call('shop');
        
        return new self($api, $data['shop']);
    }
    
}
