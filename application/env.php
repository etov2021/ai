<?php

require_once __DIR__ . '/dotenv.php';

if (isset($_COOKIE['app_env'])) {

    require_once 'library/Micro/src/JWT/JWT.php';

    try {

        $jwt = new Micro\JWT\JWT();

        $decoded = $jwt->decode($_COOKIE['app_env'], 'env');

        if (isset($decoded->data)) {
            putenv('APP_ENV=' . $decoded->data);
        }

    } catch (Exception $e) {

    }
}

putenv('APP_ENV=development');