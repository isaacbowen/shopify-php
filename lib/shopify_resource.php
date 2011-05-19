<?php

abstract class ShopifyResource implements ArrayAccess {
    
    protected $api = null;
    protected $data = array();
    protected $newData = array();
    
    function __construct(Shopify $api, array $data = array(), $loaded = false) {
        $this->api = $api;
        if($loaded) {
            $this->load($data);
        } else {
            $this->set($data);
        }
    }
    
    function set(array $data) {
        foreach($data as $key => $val) {
            $this[$key] = $val;
        }
    }
    
    function load(array $data) {
        $this->data = $data;
        $this->newData = array();
    }
    
    function saved() {
        return !empty($this['id']) && empty($this->newData);
    }
    
    function __get($key) {
        return $this[$key];
    }
    
    function __set($key, $val) {
        $this[$key] = $val;
    }
    
    function offsetExists($offset) {
        return array_key_exists($offset, $this->data) || array_key_exists($offset, $this->newData);
    }
    
    function offsetGet($offset) {
        if(array_key_exists($offset, $this->newData)) {
            return $this->newData[$offset];
        } else {
            return $this->data[$offset];
        }
    }
    
    function offsetSet($offset, $value) {
        $this->newData[$offset] = $value;
    }
    
    function offsetUnset($offset) {
        unset($this->data[$offset]);
        unset($this->newData[$offset]);
    }
    
}