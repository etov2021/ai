<?php

return [
    'config_cache_enabled' => false,
    'microloader' => [
        'paths' => [
            'Ai' => 'library/Ai',
        ],
    ],
    'navigation' => [
        'cache' => true,
    ],
    'micro_debug' => [
        'handlers' => [
            'dev_tools' => 0,
            'fire_php' => 1,
            'fire_php_explain' => 1,
            'performance' =>  __DIR__ . '/../../data/classes.php',
        ],
    ],
    'jwt' => [
        'key' => 'JdUQXOmQVYrCLTk417mjQZmyXGglloq2',
        'exp' => 31557600,
        'auth_exp' => 300,
    ],
    'error' => [
        'App\Controller\Error@index',
        '~^admin~i' => 'App\Controller\Admin\Error@index',
        '~^dev~i' => 'Dev\Controller\Error@index',
    ],
    'response' => [
        //'protocol' => '1.0',
        //'responseChunkSize' => 10000,
    ],
    'view' => [
        'paths' => [
            'shared'    => 'application/resources',
            'admin'     => 'application/resources/shared/admin',
            'site'      => 'application/resources/shared/site',
            'paginator' => 'application/resources/shared/paginator',
            'modules'   => 'application/modules',
            'default'   => 'application/resources/layouts/default',
        ],
    ],
    'acl' => [
        'enabled' => 1,
    ],
    'auth' => [
        'namespace' => [
            '~^(.{2}\/)?admin~i' => 'admin_auth',
        ],
        'resolver' => [
            'strategy' => 'query',
        ],
        '2fa' => 0,
        'forceLogout' => false,
        'refresh_time' => true,
    ],
    '2fa' => [
        'ip' => [
            'localhost',
            '127.0.0.1',
            '::1',
            '78.130.227.19',
            '87.126.16.115',
            '93.152.153.93',
            '194.12.245.77',
            '89.215.107.41', // a1 vchavdarov
        ],
    ],
    'log' => [
        'user_actions' => 1,
        'enabled' => 1,
        'strict' => 0,
        'path' => __DIR__ . '/../../data/log',
    ],
    'session' => [
        'name' => 'DEMO_OPROGRAMS',
        'cookie_httponly' => true,
        //'save_path' =>  __DIR__ . '/../../data/session',
        //'save_handler' => Micro\Session\SaveHandler\Table::class
    ],
    'language' => [
        'default' => 'bg',
        'multilingual' => true,
        'present_always' => false,

    ],
    'translator' => [
        'adapter' => 'Database',
        'options' => [
            'model' => Translations\Model\Translations::class,
            'logTranslations' => false,
        ],
    ],
    'php_config' => [
        'date.timezone' => 'Europe/Sofia',
    ],
];