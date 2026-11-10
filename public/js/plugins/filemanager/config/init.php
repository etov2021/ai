<?php

$old = getcwd();

$baseUrl = '/';

try {

    chdir(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

    if (is_file('application/env.php')) {
        require 'application/env.php';
    }

    require 'application/helpers.php';

    require 'library/Micro/autoload.php';

    if (is_file($composer = 'vendor/autoload.php')) {
        require $composer;
    }

    MicroLoader::register();

    if ((is_file($classes = 'data/classes.php')) === \true) {
        MicroLoader::setFiles(include $classes);
    }

    $container = include 'application/container.php';

    $app = new Micro\Application\Application($container);

    $app->boot();

    $app->marshalConfigKeys();

    Micro\Session\Session::start(true);

    $storageKey = 'admin_auth';

    Micro\Auth\Auth::getInstance()->setStorage(
        new Micro\Auth\Storage\Session($storageKey)
    );

    $identity = Micro\Auth\Auth::identity();

    if (!$identity || !is_allowed('fileeditor')) {
        die('Access denied.');
    }

    $groupsModel = new UserManagement\Model\Groups();
    $groupsModel->addWhere('alias', $identity->getGroups());

    $_SESSION['RF']['subfolder'] = '';

    foreach ($groupsModel->getItems() as $group) {
        if ($group['fileManagerPersonalFolder']) {
            $_SESSION['RF']['subfolder'] = $group['alias'];
            break;
        }
    }

    $baseUrl = str_replace('js/plugins/filemanager', '', base_url());

} catch (Exception $e) {
    die('Application failed.');
}

chdir($old);

return $baseUrl;