<?php

return [
    'cache' => [
        'default'  => 'file',
        'adapters' => [
            'file' => [
                'frontend' => [
                    'adapter' => 'Core',
                    'options' => [
                        'lifetime' => (3600 * 24),
                        'automatic_serialization' => 1,
                    ],
                ],
                'backend' => [
                    'adapter' => 'File',
                    'options' => [
                        'cache_dir' =>  __DIR__ . '/../../data/cache',
                    ],
                ],
            ],
            'json' => [
                'frontend' => [
                    'adapter' => 'Json',
                    'options' => [
                        'lifetime' => (3600 * 24),
                    ],
                ],
                'backend' => [
                    'adapter' => 'File',
                    'options' => [
                        'cache_dir' =>  __DIR__ . '/../../data/cache',
                        'file_name_prefix' => 'Micro_Cache_Json',
                    ],
                ],
            ],
            'db' => [
                'frontend' => [
                    'adapter' => 'Json',
                    'options' => [
                        'lifetime' => (3600 * 24),
                    ],
                ],
                'backend' => [
                    'adapter' => 'File',
                    'options' => [
                        'cache_dir' =>  __DIR__ . '/../../data/cache.db',
                        'file_name_prefix' => 'Micro_Cache_Json',
                    ],
                ],
            ],
        ],
    ],
];