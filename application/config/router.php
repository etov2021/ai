<?php

return [
    'router' => [
        'routes' => [
            'home' => [
                'pattern' => '/',
                'handler' => 'App\Controller\Index@index',
                'description' => 'Начало',
            ],
            // ADMIN routes
			'admin-home' => [
                'pattern' => '/admin',
                'handler' => 'App\Controller\Admin\Index@index',
                'description' => 'Админ - начало',
            ],
            'admin-login' => [
                'pattern' => '/admin/login',
                'handler' => 'UserManagement\Controller\Admin\Index@login',
                'description' => 'Админ - вход',
            ],
            'admin-login2' => [
                'pattern' => '/admin/login2',
                'handler' => 'UserManagement\Controller\Admin\Index@login2',
                'description' => 'Админ - вход 2',
            ],
            'admin-logout' => [
                'pattern' => '/admin/logout',
                'handler' => 'UserManagement\Controller\Admin\Index@logout',
                'description' => 'Админ - изход',
            ],
            'admin-register' => [
                'pattern' => '/admin/login',
                'handler' => 'UserManagement\Controller\Admin\Index@register',
                'description' => 'Админ - регистрация',
            ],
            'admin' => [
                'default_route' => true,
                'literal' => 'admin',
                'namespace' => 'Controller\\Admin\\',
                'description' => 'Админ',
            ],
            // SITE Routes
            'file-download' => [
                'pattern' => '/file/download/{hash}',
                'handler' => 'Files\Controller\Download@index',
                'description' => 'Файл - сваляне',
            ],
            // DEFAULT route
            'default' => [
                'default_route' => true,
                'literal' => '',
                'namespace' => 'Controller\\',
                'description' => 'Стандартен път - по подразбиране',
                'order' => 1000,
            ],
        ],
    ],
];