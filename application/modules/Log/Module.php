<?php

namespace Log;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Event\Message;
use Psr\Log\LoggerInterface;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if ($manager instanceof Manager && $container->get('config')['log.user_actions']) {

            $manager->attach(['*.Model.*.After.Save', '*.Model.*.After.Delete', '*.Model.*.After.Activate'], function (Message $message) use ($container) {

                if (!$container->has('logger_db')) {
                    return;
                }

                if (!$message->model->getEnableLog()) {
                    return;
                }

                $logger = $container->get('logger_db');

                if ($logger instanceof LoggerInterface) {
                    $entity = $message->entity;
                    $originalData = [];
                    if ($message->originalData !== null) {
                        $originalData = $message->originalData;
                    }
                    $logger->info($entity[$message->model->getIdentifier()], ['ENTITY' => get_class($entity), 'EVENT' => $message->getEvent()] + $entity->toArray() + ['ORIGINAL_DATA' => $originalData]);
                }
            });

            $manager->attach(['log'], function (Message $message) use ($container) {

                if (!$container->has('logger_db')) {
                    return;
                }

                $logger = $container->get('logger_db');

                if ($logger instanceof LoggerInterface) {
                    $logMessage = $message->getParam('message');
                    $logContext = $message->getParam('context');
                    $level      = $message->getParam('level') ?: 'info';
                    $logger->log($level, ($logMessage ?: $message->getEvent()), (array) $logContext);
                }
            });
        }
    }

    public function getConfig()
    {
        return [
            'dependencies' => [
                'services' => [
                    'logger_db' => Factory\LoggerDb::class,
                ],
            ],
        ];
    }
}
