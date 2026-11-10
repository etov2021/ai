<?php

use Micro\Application\Application;

$app = new Application(
    include __DIR__ . '/container.php'
);

return $app;