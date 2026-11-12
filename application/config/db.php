<?php

return [
    'db' => [
        'default' => 'localhost',
        'set_default_adapter' => true,
        'set_default_cache' => 'db',
        'adapters' => [
            'localhost' => [
                 'adapter' => 'mysqli',
                'host' => getenv('DB_HOST') ?: '',
                'dbname' => getenv('DB_DBNAME') ?: '',
                'username' => getenv('DB_USERNAME') ?: '',
                'password' => getenv('DB_PASSWORD') ?: '',
                 'charset' => 'utf8',
                 'profiler' => [
                    'enabled' => 1,
                ],
            ],
        ],
    ],
];