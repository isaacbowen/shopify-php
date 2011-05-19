<?php

// this is the only file you need to include.


// define an autoloader for library files
spl_autoload_register(function($name) {
    $filename = strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $name));
    if(file_exists(dirname(__FILE__ ). "/lib/$filename.php")) {
        require_once(dirname(__FILE__ ). "/lib/$filename.php");
    }
});