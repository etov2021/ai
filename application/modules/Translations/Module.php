<?php

namespace Translations;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Event\Message;
use Micro\Form\Form;
use Micro\Form\Element\Submit;
use Micro\Model\Entity\TranslationInterface as EntityTranslationInterface;
use Micro\Model\ModelInterface;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        if ($container->get('config')->get('language.multilingual')) {
            $provider = new Multilingual\Provider($container);
            $provider->register();
        }

        $manager = $container->get('event');

        if (!$manager instanceof Manager) {
            return;
        }

        $manager->attach('Crud.Form.Prepare', function (Message $message) use ($container) {

            $form   = $message->form;
            $entity = $message->entity;
            $model  = $message->model;

            if ($form instanceof Form
                && $model instanceof ModelInterface
                && $entity instanceof EntityTranslationInterface
                && $entity[$model->getIdentifier()]
                && $entity['languageId'] != $container->get('default_language')->getId()
                && $entity->hasTranslation($container->get('current_language'))
            ) {
                $form->addElement(new Submit('btnDeleteTranslation', [
                    'value' => 'Изтриване на превод',
                    'class' => 'btn btn-danger',
                    'attributes' => [
                        'data-handler' => 'delete-translation'
                    ]
                ]));
            }
        });
    }

    public function getConfig()
    {
        return [];
    }
}
