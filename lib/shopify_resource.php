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

    // viable methods
    protected $resource_methods = array();
    
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

    function __call($method, $args) {
        $this_reflection = new ReflectionObject($this);
        if(in_array($method, $this->resource_methods) && $this_reflection->hasMethod("resource_$method")) {
            return call_user_func_array(array($this, "resource_$method"), $args);
        } else {
            throw new ShopifyResourceException("Method $method is unsupported for " . get_class($this));
        }
    }

    static function __callStatic($method, $args) {
        $class = get_called_class();
        $type = self::class_to_type($class);

        // first arg must always be an instance of Shopify
        if(!($args[0] instanceof Shopify)) {
            throw new ShopifyResourceException("$class::$method requires an instance of Shopify as the first argument");
        }
        $api = $args[0];

        if($method == $type) {
            // singular get (e.g. ShopifyProduct::product(1234))
            $data = $api->call($type . 's/' . $args[1]);
            return new $class($api, $data[$type], true);
        } elseif($method == $type . 's') {
            // plural get (e.g. ShopifyProduct::products(array('page' => 2))
            $data = $api->call($type . 's', $args[1]);
            $objects = array();
            foreach($data[$type . 's'] as $object_data) {
                $objects[] = new $class($api, $object_data, true);
            }
            return $objects;
        } else {
            throw new ShopifyResourceException("Unsupported method $class::$method");
        }
    }

    protected function resource_save() {
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

    protected function resource_delete() {
        if(empty($this['id']) || $this->deleted) return false;

        $this->api->call($this->type(true) . '/' . $this['id'], array(), 'delete');
        $this->deleted = true;
        return true;
    }
    

    // misc helpers

    // returns lowercase, underscored resource type for use with REST requests. uses simple pluralization.
    protected static function class_to_type($class, $plural = false) {
        return strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', preg_replace('/^Shopify(.*)$/', '$1', $class))) . ($plural ? 's' : '');
    }
    
    // object implementation for class_to_type
    function type($plural = false) {
        return self::class_to_type(get_class($this), $plural);
    }


    // data retrieval
    
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
