<?php

declare(strict_types=1);

// library/Ai е независима от Micro, затова тестовете имат собствен PSR-4 автозареждач.
spl_autoload_register(static function (string $class): void {
    foreach (['Ai\\' => __DIR__ . '/../library/Ai/', 'Ai\\Tests\\' => __DIR__ . '/Ai/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
