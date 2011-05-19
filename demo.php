<!DOCTYPE html>
<html>
<head>
    <title>Shopify API Test</title>
    <style type="text/css">
    body {
        font-family: sans-serif;
        margin: 2em 1em;
        color: #222;
    }
    </style>
</head>
<body>
<?php

require_once('shopify.php');

try {
    // setup
    Shopify::setup('9ba1eeb66cbb1f0ce0c61f98d935cc94', 'b579f7bcadfb85effab7d92ddb9702aa');
    
    // do we have a shop to work with?
    if(empty($_GET['shop'])) {
        
        // authenticate
        if(!empty($_POST['shop'])) {
            header('Location: https://' . $_POST['shop'] . '/admin/api/auth?api_key=' . Shopify::$api_key);
            exit();
        }
        
        echo '<form action="demo.php" method="post"><label for="shop">Shop:</label> <input type="text" name="shop"> <input type="submit" value="Authenticate" /></form>';
        
    } else {
        
        // run demo
        $api = new Shopify($_REQUEST['shop'], $_REQUEST['timestamp'], $_REQUEST['signature'], $_REQUEST['t']);
        
        // load shop
        $shop = $api->shop();
        echo '<h1>' . $shop->name . '</h1>';
        
        echo '<h2>' . $api->products_count() . ' Products</h2>';
        
        echo '<ul>';
        foreach($api->products() as $product) {
            echo '<li>' . $product['id'] . ': ' . $product->title;
            echo '<ul>';
            foreach($product->variants as $variant) {
                echo '<li>$' . $variant->price . ': ' . $variant->title . '</li>';
            }
            echo '</ul></li>';
        }
        echo '</ul>';
    }
} catch(Exception $e) {
    echo get_class($e) . ': ' . $e->getMessage();
}

?>
</body>
</html>