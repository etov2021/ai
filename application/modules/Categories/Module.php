<?php

namespace Categories;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Categories\Model\Entity\CategoryInterface;
use Micro\Event\Message;
use Micro\Event\Manager;
use Micro\Form\Element;
use Categories\Model\Categories;
use Micro\Container\SharedContainer;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if (!$manager instanceof Manager) {
            return;
        }

        $manager->attach('*.Model.*.Before.Save', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof CategoryInterface) {
                $settings = $entity->getCategorySettings() + ['isArray' => 0];
                if ($settings['isArray']) {
                    $ids = $entity->getCategoryId();
                    if ($ids && !\is_array($ids)) {
                        $ids = [$ids];
                    }
                    $entity->setCategoryId($ids ? \implode(',', $ids) : \null);
                }
            }
        });

        $manager->attach('*.Model.*.Load', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof CategoryInterface) {
                $settings = $entity->getCategorySettings() + ['isArray' => 0];
                if ($settings['isArray']) {
                    $ids = $entity->getCategoryId();
                    $entity->setCategoryId($ids ? \explode(',', $ids) : []);
                }
            }
        });


        $manager->attach('Crud.Form.Prepare', function (Message $message) {

            $model  = $message->model;
            $entity = $message->entity;

            if ($entity instanceof CategoryInterface) {

                $settings = $entity->getCategorySettings() + ['isArray' => 0];

                if (isset($settings['parent']) && $settings['parent']) {
                    $treeHelper = new \Categories\Helper\Tree($settings['target']);
                    if (!isset($settings['leaf']) || !$settings['leaf']) {
                        $options = $treeHelper->flat($treeHelper->getTree(), ' - ');
                    } else {
                        $options = $treeHelper->toSelectOptions($treeHelper->getTree(), ' - ');
                    }
                } else {
                    $domainId = 0;
                    try {
                        $domain = SharedContainer::getInstance()->get('domain');
                        if ($domain) {
                            $domainId = $domain->getId();
                        }
                    } catch (\Exception $e) {

                    }
                    $options = (new Categories())->useUiLanguage()->setSettings($settings)->fetchCachedPairs(['domainId' => $domainId], \null, ['order' => 'asc']);
                }

                $settings = $settings + [
                    'label' => 'Категория',
                    'labelClass' => 'control-label',
                    'class' => 'form-control select2',
                    'required' => 1,
                    'emptyOption' => 'Избери',
                    'multiOptions' => $options,
                    'value' => $entity->getCategoryId(),
                ];

                if ($settings['isArray']) {
                    unset($settings['emptyOption']);
                }

                $message->form->addElement(new Element\Select('categoryId', $settings));
            }
        });
    }
}
