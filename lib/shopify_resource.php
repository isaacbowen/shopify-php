<?php

abstract class ShopifyResource implements ArrayAccess {
    
    // instance of Shopify
    protected $api = null;

    // contains assoc array of data for this resource, accessed via ArrayAccess
    protected $data = array();

    // new data gets set here, to be saved at some point
    protected $newData = array();

    // state flags
    protected $deleted = false;

    // 'shop' is singular, 'product' is not
    protected static $resource_singular = false;
    
    function __construct(Shopify $api, array $data = array(), $loaded = false) {
        $this->api = $api;
        if($loaded) {
            $this->load($data);
        } else {
            $this->set($data);
        }
    }
    
    // set data in bulk using ArrayAccess (i.e. ->newData)
    function set(array $data) {
        foreach($data as $key => $val) {
            $this[$key] = $val;
        }
    }
    
    // overwrite data array directly, and reset newData
    function load(array $data) {
        $this->data = $data;
        $this->newData = array();
    }
    
    function saved() {
        return !empty($this['id']) && empty($this->newData);
    }

    // read/write from api

    function save() {
        if(self::$resource_singular) throw new ShopifyException('save() not applicable for singular ' . $this->type());

        $params = array($this->type() => $this->newData);

        if(!empty($this['id'])) {
            $data = $this->api->call($this->type(true) . '/' . $this['id'], $params, 'put');
            $this->load($data[$this->type()]);
        } else {
            $data = $this->api->call($this->type(true), $params, 'post');
            $this->load($data[$this->type()]);
        }

        return true;
    }

    function delete() {
        if(self::$resource_singular) throw new ShopifyException('delete() not applicable for singular ' . $this->type());

        if(empty($this['id']) || $this->deleted) return false;

        $this->api->call($this->type(true) . '/' . $this['id'], array(), 'delete');
        $this->deleted = true;
        return true;
    }


    // misc helpers

    // returns lowercase, underscored resource type for use with REST requests. uses simple pluralization.
    function type($plural = false) {
        return strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', preg_replace('/^Shopify(.*)$/', '$1', get_class($this)))) . ($plural ? 's' : '');
    }


    // data retrieval
    
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
