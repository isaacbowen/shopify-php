<?php

class Shopify {

    // config
    var $ssl = true;
    var $mode = 'json';
    var $shop = '';
    var $prefix = '/admin';
    
    // api creds
    static $api_key = '';
    static $api_secret = '';
    
    // auth
    private $auth_user = '';
    private $auth_password = '';
    
    // limits
    static $api_limit = 0;
    private $api_shop_limit = 0;
    
    // extra options
    static $options = array(
        'throttle_mode' => 'exception', // 'sleep' else 'exception'
    );
    
    // curl resources
    private $curl_handle = null;
    private $curl_response_headers = array();
    private $curl_response_info = array();
    private $curl_response_content = '';
    
    // response modes
    private static $modes = array(
        'xml' => 'application/xml',
        'json' => 'application/json',
    );
    
    
    // set up api credentials
    static function setup($api_key, $api_secret, $options = array()) {
        self::$api_key = $api_key;
        self::$api_secret = $api_secret;
    }
    
    // instantiate for a particular shop
    function __construct($shop, $timestamp, $signature, $token, $password = '') {
        // prep auth
        $this->auth_user = self::$api_key;
        
        // either save or generate a password
        if(!empty($password)) {
            $this->auth_password = $password;
        } else {
            // validate signature
            if(md5(self::$api_secret . "shop={$shop}t={$token}timestamp={$timestamp}") != $signature) {
                throw new ShopifyAuthException("Invalid signature: $signature");
            }
            
            // generate password hash
            $this->auth_password = md5(self::$api_secret . $token);
        }
        
        // shop?
        $this->shop = $shop;
        
        // prep curl
        $this->curl_handle = curl_init();
        curl_setopt_array(
            $this->curl_handle,
            array(
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADERFUNCTION => array($this, 'curl_read_headers'),
            )
        );
    }
    
    // sent off requests to the appropriate resource
    function __call($name, $args) {
        // figure out which class we're dealing with
        $class_name = 'Shopify' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if(!class_exists($class_name)) {
            $class_name = rtrim($class_name, 's');
            if(!class_exists($class_name)) {
                throw new ShopifyException('Unknown resource ' . $class_name);
            }
        }
        
        // invoke
        array_unshift($args, $this);
        return call_user_func_array(array($class_name, $name), $args);
    }
    
    // accessor for child resources
    
    function __get($name) {
        $class = 'Shopify' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if(!class_exists($class)) {
            $class = preg_replace('/s$/', '', $class);
            if(!class_exists($class)) {
                return null;
            }
        }
        
        return new ShopifyResourceHandle($this, $class);
    }
    
    // perform an api call
    function call($path, $params = array(), $method = 'get') {
        // clean path
        $path = ltrim($path, '/');
        
        // set mode
        $mode = $this->mode;
        if(preg_match('/^(.*)\.(json|xml)$/i', $path, $mode_matches)) {
            $path = $mode_matches[1];
            $mode = $mode_matches[2];
        }
        
        // prep method
        $method = strtoupper($method);
        
        // prep request data
        $url = ($this->ssl ? 'http://' : 'https://') . "{$this->auth_user}:{$this->auth_password}@{$this->shop}{$this->prefix}/$path.$mode";
        if($method == 'GET') {
            if(!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        } else {
            switch($mode) {
                case 'json':
                    $post_data = json_encode($params);
                    break;
                case 'xml':
                    $post_data = self::arrayToXML($params);
                    break;
                default:
                    throw new ShopifyResponseException("Unhandled mode: $mode");
            }
            
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $post_data);
        }

        // set request data
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, array('Content-type: ' . self::$modes[$mode]));
        
        // go time
        $this->curl_response_content = curl_exec($this->curl_handle);
        $this->curl_response_info = curl_getinfo($this->curl_handle);
        
        // check on api limit
        foreach(array('http_x_shopify_api_call_limit', 'http_x_shopify_shop_api_call_limit') as $api_call_limit_header) {
            $api_call_limit = $this->curl_header($api_call_limit_header);
            if(empty($api_call_limit)) continue;
            
            list($api_count, $api_limit) = explode('/', $api_call_limit, 2);
            
            // are we in excess of a limit?
            if($api_count >= $api_limit) {
                switch(self::$options['throttle_mode']) {
                    case 'sleep':
                        // todo implement
                        break;
                    default:
                        throw new ShopifyLimitException("Hit the Shopify API limit: $api_call_limit");
                        break;
                }
            }
        }
        
        // process response
        $response = null;
        if(strstr($this->curl_info('content_type'), self::$modes[$mode]) !== false) {
            switch($mode) {
                case 'json':
                    $response = json_decode($this->curl_content(), true);
                    break;
                case 'xml':
                    $response = simplexml_load_string($this->curl_content());
                    break;
                default:
                    throw new ShopifyResponseException("Unhandled mode: $mode");
            }
        }
            
        if(floor($this->curl_info('http_code')/100) == 2) {
            // success
            return $response;
        } else {
            // failure
            
            // prepare error message
            $message = 'Shopify returned status ' . $this->curl_info('http_code') . " for $method /" . end(explode('/', $this->curl_info('url'), 4));
            if(!empty($response)) {
                switch($mode) {
                    case 'json':
                        $message = implode(', ', $response);
                        break;
                    case 'xml':
                        $message = array();
                        foreach($response->error as $error) {
                            $message[] = (string)$error;
                        }
                        $message = implode(', ', $message);
                        break;
                }
            }
            
            // throw the appropriate exception
            switch($this->curl_info('http_code')) {
                case 401:
                    throw new ShopifyAuthException($message, 401);
                    break;
                default:
                    throw new ShopifyResponseException($message, $this->curl_info('http_code'));
            }
        }
    }
    
    
    // misc
    
    function password() {
        return $this->auth_password;
    }
    
    
    // curl helpers
    
    function curl_info($key) {
        if(array_key_exists($key, $this->curl_response_info)) {
            return $this->curl_response_info[$key];
        }
        
        return null;
    }
    
    function curl_content() {
        return $this->curl_response_content;
    }
    
    function curl_header($key) {
        // integer key
        if(is_int($key) && array_key_exists($key, $this->curl_response_headers)) {
            return $this->curl_response_headers[$key];
        }
        
        // string key
        if(array_key_exists(strtoupper($key), $this->curl_response_headers)) {
            return $this->curl_response_headers[strtoupper($key)];
        }
        
        // unknown
        return false;
    }
    
    private function curl_read_headers($ch, $headers) {
        // load headers
        foreach(explode("\n", $headers) as $header) {
            if(trim($header) == '') continue;
            
            if(preg_match('/^([\w\-]+): (.*)$/', $header, $header_matches)) {
                // header keys are stored uppercase
                $this->curl_response_headers[strtoupper($header_matches[1])] = trim($header_matches[2]);
            } else {
                $this->curl_response_headers[] = trim($header);
            }
        }
        
        return strlen($headers);
    }
    
    
    // from original Shopify PHP API
    static function arrayToXML($array, $xml = '', $specialCaseTag = ''){
        if($xml == '') $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $specialCases = array(
            'variants' => 'variant',
            'images' => 'image',
            'options' => 'option',
            'line-items' => 'line-item'
        );
        foreach($array as $k => $v){
            if(is_numeric($k) && !isEmpty($specialCaseTag)) $k = $specialCaseTag;
            if(is_array($v)) {
                if(array_key_exists($k, $specialCases)){
                    $xml .= '<' . $k . ' type="array">';
                    $xml = self::arrayToXML($v, $xml, $specialCases[$k]);
                }else{
                    $xml .= '<' . $k . '>';
                    $xml = self::arrayToXML($v, $xml);
                }
                $xml .= '</' . $k . '>';
            } else {
                $xml .= '<' . $k . '>' . $v . '</' . $k . '>';
            }
        }	
        return $xml;
    }

}
