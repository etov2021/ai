<?php

// Общи SMTP настройки за Symfony Mailer и Swift; стойностите са в .env (MAIL_HOST, MAIL_PORT, MAIL_SECURITY, MAIL_USERNAME, MAIL_PASSWORD).
$smtp = array_filter([
    'host' => getenv('MAIL_HOST') ?: 'localhost',
    'port' => (int) (getenv('MAIL_PORT') ?: 25),
    'security' => getenv('MAIL_SECURITY') ?: null,
    'username' => getenv('MAIL_USERNAME') ?: null,
    'password' => getenv('MAIL_PASSWORD') ?: null,
], static fn ($v) => $v !== null);

return [
    'mail' => [
        'testEmails' => [],
        'enable' => 0,
        'mailer' => Email\Mailer\Symfony::class,
        //'mailer' => Email\Mailer\Swift::class,
        'message' => [
            'from' => [
                'icygen.developers@gmail.com' => 'Developer'
            ],
        ],
        // SWIFT config
        'swift_transport' => [
            'type' => 'smtp',
            'options' => $smtp,
        ],
        // Symfony Mailer: type smtp | sendmail; вместо host/port може да се зададе 'dsn' => 'smtp://user:pass@host:587'
        'symfony_transport' => [
            'type' => 'smtp',
            'options' => $smtp,
        ],
    ],
];