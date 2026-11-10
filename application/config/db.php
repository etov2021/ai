<?php

return [
    'db' => [
        'default' => 'localhost',
        'set_default_adapter' => true,
        'set_default_cache' => 'db',
        'adapters' => [
            'localhost' => [
                 'adapter' => 'mysqli',
                 'host' => 'localhost',
                 'dbname' => 'ai',
                 'username' => 'root',
                 'password' => '',
                 'charset' => 'utf8',
                 'profiler' => [
                    'enabled' => 1,
                ],
            ],
        ],
    ],
];