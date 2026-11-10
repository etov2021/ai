<?php

use Micro\Application\Utils;

$cachedConfigFile = __DIR__ . '/../data/app_config.php';

$config = [];

if (is_file($cachedConfigFile)) {
    // Try to load the cached config
    $config = include $cachedConfigFile;
} else {
    // Load configuration from autoload path
    foreach (glob(sprintf(__DIR__ . '/{config/*.php,config/%1$s/*.php,config/*.json,config/%1$s/*.json}', env()), GLOB_BRACE) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $data = @json_decode(file_get_contents($file), true);
            if (empty($data)) {
                continue;
            }
        } else {
            $data = include $file;
        }
        $config = Utils::merge($config, $data);
    }
    // Cache config if enabled
    if (isset($config['config_cache_enabled']) && $config['config_cache_enabled'] === true) {
        file_put_contents($cachedConfigFile, '<?php return ' . var_export($config, true) . ';');
    }
}

return $config;