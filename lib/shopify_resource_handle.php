<?php

class ShopifyResourceHandle {
    
    private $api;
    
    private $child_class;
    private $child_reflection;
    private $parent_class;
    
    
    function __construct(Shopify $api, $child_class, $parent_class = null) {
        if(!class_exists($child_class)) {
            throw new ShopifyResourceException("Unknown resouce type $child_class");
        }
        if($parent_class !== null && !class_exists($parent_class)) {
            throw new ShopifyResourceException("Unknown resouce type $parent_class");
        }
        
        $this->api = $api;
        $this->parent_class = $parent_class;
        $this->child_class = $child_class;
        $this->child_reflection = new ReflectionClass($child_class);
    }
    
    function __call($name, $args) {
        if(!$this->child_reflection->hasMethod($name)) {
            throw new ShopifyResourceException("Resource {$this->child_class} has no method $name");
        }

        $method = $this->child_reflection->getMethod($name);
        if(!$method->isStatic()) {
            throw new ShopifyResourceException("Unsupported access for {$this->child_class}'s method $name");
        }
        
        array_unshift($args, $this->api);
        return call_user_func_array(array($this->child_class, $name), $args);
    }
    
}
