<?php

namespace Nomenclatures;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Event\Message;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if ($manager instanceof Manager) {

            $manager->attach(['Nomenclatures.Model.*.Before.Save'], function (Message $message) use ($container) {

                $entity = $message->getParam('entity');

                if (isset($entity['updated'])) {
                    $entity['updated'] = date('Y-m-d H:i:s');
                }

                if (isset($entity['created']) && empty($entity['created'])) {
                    $entity['created'] = date('Y-m-d H:i:s');
                }

                $auth = identity();

                if ($auth) {
                    if (isset($entity['ownerId']) && empty($entity['ownerId'])) {
                        $entity['ownerId'] = $auth->getId();
                    }

                    if (isset($entity['editorId'])) {
                        $entity['editorId'] = $auth->getId();
                    }
                }

            });
        }
    }
}
