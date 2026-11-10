<?php

namespace App;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Model\DatabaseAbstract;
use Micro\Event\Manager;
use Micro\Event\Message;
use Micro\Database\Expr;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        DatabaseAbstract::setDefaultCache(cache('json'));

        $phpConfig = $container->get('config')->get('php_config');

        if (is_array($phpConfig)) {
            foreach ($phpConfig as $k => $v) {
                ini_set($k, $v);
            }
        }

        $manager = $container->get('event');

        if ($manager instanceof Manager) {

            $manager->attach(['*.Model.*.Before.Save'], function (Message $message) use ($container) {

                $entity = $message->getParam('entity');

                if (isset($entity['updated'])) {
                    $entity['updated'] = new Expr('NOW()');
                }

            });
        }
    }
}
