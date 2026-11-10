<?php

namespace Domains;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Event\Message;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Domains;

use Micro\Model\ModelInterface;
use Micro\Form\Element\Select;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if (!$manager instanceof Manager) {
            return;
        }

        $manager->attach('*.Model.*.Load', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof DomainInterface) {
                $domains = $entity->getDomains();
                if (is_array($domains)) {
                    $domains = implode(',', $domains);
                }
                $entity->setDomains($domains ? explode(',', $domains) : []);
            }
        });

        $manager->attach('*.Model.*.Before.Save', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof DomainInterface) {
                $domains = $entity->getDomains();
                if ($domains && !is_array($domains)) {
                    $domains = [$domains];
                }
                $entity->setDomains($domains ? implode(',', $domains) : null);
            }
        });

        $manager->attach('Crud.Form.Prepare', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof DomainInterface) {
                $settings = $entity->getDomainSettings() + ['domain_depended' => 1];
                if (!$settings['domain_depended']) {
                    $settings = $settings + [
                        'label' => 'Domain',
                        'labelClass' => 'control-label',
                        'class' => 'form-control select2',
                        'isArray' => 1,
                        'attributes' => [
                            'id' => 'domains',
                            'data-placeholder' => translate('Select domain'),
                        ],
                        'multiOptions' => (new Domains())->fetchCachedPairs(null, null, ['id' => 'asc']),
                        'value' => $entity->getDomains(),
                    ];
                    $message->form->addElement(new Select('domains', $settings));
                }
            }
        });

        $manager->attach('Crud.Model.Prepare', function (Message $message) use ($container) {
            $model = $message->model;
            if ($model instanceof ModelInterface) {
                $testEntity = $model->createEntity();
                if ($testEntity instanceof DomainInterface) {
                    $settings = $testEntity->getDomainSettings() + ['domain_depended' => 1];
                    if ($settings['domain_depended']) {
                        try {
                            $domain = $container->get('domain');
                            if ($domain) {
                                $model->addWhere('domainId', $domain->getId());
                            }
                        } catch (\Exception $e) {

                        }
                    }
                }
            }
        });

        $manager->attach(['*.Model.*.Load', '*.Model.*.Before.Delete', '*.Model.*.Before.Activate'], function (Message $message) use ($container) {
            $entity = $message->entity;
            if ($entity instanceof DomainInterface) {
                $settings = $entity->getDomainSettings() + ['domain_depended' => 1];
                if ($settings['domain_depended'] && $container->has('domain')) {
                    $domain = $container->get('domain');
                    if ($domain && $domain->getId() != $entity->getDomainId()) {
                        throw new \Exception('Записът е за друг домейн', 403);
                    }
                }
            }
        });

        $manager->attach('*.Model.*.Before.Save', function (Message $message) use ($container) {
            $entity = $message->entity;
            if ($entity instanceof DomainInterface) {
                $settings = $entity->getDomainSettings() + ['domain_depended' => 1];
                if ($settings['domain_depended']) {
                    try {
                        $domain = $container->get('domain');
                        if ($domain) {
                            $entity->setDomainId($domain->getId());
                        }
                    } catch (\Exception $e) {

                    }
                }
            }
        });
    }

    public function getConfig()
    {
        return [];
    }
}
