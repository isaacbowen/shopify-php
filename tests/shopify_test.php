<?php

class ShopifyTest extends PHPUnit_Framework_TestCase {
    
    protected $api = null;
    
    protected function setUp() {
        Shopify::setup('9ba1eeb66cbb1f0ce0c61f98d935cc94', 'b579f7bcadfb85effab7d92ddb9702aa');
        $this->api = new Shopify('stroman-huels-and-bartoletti1621.myshopify.com', '1305780988', '112690b2ae75e7876ad6e2ae6d3d78cb', 'ae98bcf86ce3dd661d48e18251ba3d3b');
    }
    
    function testAuth() {
        $this->assertEquals('1e82b625b74c31fad791d611390dbfbc', $this->api->password());
    }
    
    /**
     * @expectedException ShopifyAuthException
     */
    function testInvalidAuth() {
        $api = new Shopify('stroman-huels-and-bartoletti1621.myshopify.com', '1305780988', 'badsignature', 'ae98bcf86ce3dd661d48e18251ba3d3b');
    }
    
    function testApi() {
        $shop = $this->api->call('shop');
        $this->assertInternalType('array', $shop);
        $this->assertArrayHasKey('shop', $shop);
        $this->assertArrayHasKey('domain', $shop['shop']);
        $this->assertEquals('stroman-huels-and-bartoletti1621.myshopify.com', $shop['shop']['domain']);
    }

    function testShop() {
        // load
        $shop = $this->api->shop();
        $this->assertInstanceOf('ShopifyShop', $shop);
        $this->assertEquals('stroman-huels-and-bartoletti1621.myshopify.com', $shop['domain']);

        // ensure that the resource_methods filter is working
        try {
            $shop->save();
            $this->fail('Expecting ShopifyResourceException');
        } catch(ShopifyResourceException $e) {
            $this->assertInstanceOf('ShopifyResourceException', $e);
        }
        try {
            $shop->delete();
            $this->fail('Expecting ShopifyResourceException');
        } catch(ShopifyResourceException $e) {
            $this->assertInstanceOf('ShopifyResourceException', $e);
        }
    }
    
    function testProduct() {
        // initial count
        $count = $this->api->products_count();
        $this->assertInternalType('integer', $count);
        
        // setup
        $product = new ShopifyProduct($this->api);
        $product->set(array(
            'title' => 'Test product',
            'vendor' => 'acme',
            'body_html' => '<strong>Good snowboard!</strong>',
            'product_type' => 'Snowboard',
        ));
        $this->assertFalse($product->saved());
        $this->assertFalse($product->delete());
        
        // save
        $this->assertTrue($product->save());
        $this->assertTrue($product->saved());
        $this->assertEquals($count+1, $this->api->products_count());
        $this->assertNotEmpty($product['id']);
        
        
        // save modification
        $product['title'] = 'Product test';
        $this->assertTrue($product->save());
        $this->assertEquals('Product test', $product['title']);
        
        // load
        $product_id = $product['id'];
        unset($product);
        $product = $this->api->product($product_id);
        $this->assertInstanceOf('ShopifyProduct', $product);
        $this->assertEquals(1, count($product->variants));
        $this->assertInstanceOf('ShopifyProductVariant', $product->variants[0]);
        $this->assertNotEmpty($product->variants[0]['id']);
        
        // delete
        $this->assertTrue($product->delete());
        $this->assertFalse($product->delete());
        $this->assertEquals($count, $this->api->products_count());
        
        // test 404 response after deleted
        try {
            $this->api->product($product['id']);
            $this->fail();
        } catch(ShopifyException $e) {
            $this->assertInstanceOf('ShopifyResponseException', $e);
            $this->assertEquals(404, $e->getCode());
        }
    }
    
}
