<?php

namespace Log\Factory;

use Micro\Container\ContainerFactoryInterface;
use Micro\Container\ContainerInterface;
use Monolog\Logger;
use Log\Monolog\Handler\PDOHandler;

class LoggerDb implements ContainerFactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string $service
     */
    public function create(ContainerInterface $container, $service)
    {
        $log = new Logger('app.db');

        $db = $container->get('config')['db'];

        if ($db === null || !isset($db['adapters'][$db['default']])) {
            return null;
        }

        $config = $db['adapters'][$db['default']];

        try {
            $log->pushHandler(new PDOHandler(@new \PDO(
                'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';port=' . (isset($config['port']) && $config['port'] ? $config['port'] : 3306) . ';charset=utf8',
                $config['username'],
                $config['password']
            )));
        } catch (\Exception $e) {
            return null;
        }

        return $log;
    }
}