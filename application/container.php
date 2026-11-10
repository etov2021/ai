<?php

use Micro\Container\Container;
use Micro\Application\Config;

$config = include __DIR__ . '/config.php';

$container = new Container();

$container->setAsGlobal();

$container->set('config', new Config($config));

return $container;