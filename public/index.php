<?php

chdir(dirname(__DIR__));

if (is_file('application/env.php')) {
    require 'application/env.php';
}

require 'application/helpers.php';

require 'library/Micro/autoload.php';

if (is_file($composer = 'vendor/autoload.php')) {
    require $composer;
}

MicroLoader::register();

if ((is_file($classes = 'data/classes.php')) === true) {
    MicroLoader::setFiles(include $classes);
}

error_reporting(E_ALL | E_NOTICE);

if (is_debug()) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

try {

    if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }

    $app = require 'application/start.php';

    $app->run();

} catch (Throwable $e) {
    if (is_debug()) {
        echo '<pre>';
        echo $e->getMessage() . "\n";
        echo $e->getTraceAsString();
    }
}