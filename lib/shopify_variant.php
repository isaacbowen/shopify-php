<?php

class ShopifyVariant extends ShopifyResource {

    protected $resource_methods = array('save', 'delete');

    static function variant($api, $id) {
        $data = $api->call("variants/$id");
        return new self($api, $data['variant'], true);
    }
    
}
