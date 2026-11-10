<?php


$middlewares = [];

if (is_debug()) {
    $middlewares['dev_tools'] = App\Middleware\DevTools\DevTools::class;
}

$middlewares = $middlewares + [
    'auth' => UserManagement\Middleware\Auth::class,
    'detect_domain' => Domains\Middleware\DetectDomain::class,
];

return [
    'middlewares' => $middlewares,
];