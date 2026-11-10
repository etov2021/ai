<?php

return [
    'mail' => [
        'testEmails' => [],
        'enable' => 0,
        'mailer' => Email\Mailer\Swift::class,
        //'mailer' => Email\Mailer\Laminas::class,
        'message' => [
            'from' => [
                'icygen.developers@gmail.com' => 'Developer'
            ],
        ],
        // SWIFT config
        'swift_transport' => [
            'type' => 'smtp',
            'options' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'security' => 'tls',
                'username' => 'icygen.developers@gmail.com',
                'password' => '!@34Qwer',
            ],
        ],
        // Laminas config
        'laminas_transport' => [
            'type' => 'smtp',
            'options' => [
                'name' => 'smtp.gmail.com',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'connection_class' => 'login',
                'connection_config' => [
                    'username' => 'icygen.developers@gmail.com',
                    'password' => '!@34Qwer',
                    'ssl'      => 'tls',
                ],
            ],
        ],
    ],
];