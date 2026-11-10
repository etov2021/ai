<?php

namespace Seo;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Form\Element;
use Micro\Event\Message;
use Seo\Model\Entity\MetaInterface;
use Seo\Model\Meta;
use Micro\Router\Route;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if (!$manager instanceof Manager) {
            return;
        }

        $manager->attach('Render.Start', function (Message $message) use ($container) {

            $view = $message->view;

            $route = $container->get('router')->getCurrentRoute();
            $meta  = null;

            if ($route instanceof Route && $container->has('domain') && $container->has('language')) {

                $language = $container->get('language');
                $domain = $container->get('domain');

                $callback = function () use ($container, $language, $domain) {

                    $sql  = '
                        SELECT *
                        FROM `MetaRoutes`
                        LEFT JOIN `MetaRoutesLang` ON `MetaRoutes`.`id` = `MetaRoutesLang`.`metaId` AND (`MetaRoutesLang`.`languageId` = ?)
                        WHERE `MetaRoutes`.`domainId` = ?
                        ORDER BY NULL
                    ';

                    $rows = $container->get('db')->fetchAll($sql, [$language->getId(), $domain->getId()]);

                    $result = [];

                    foreach ($rows as $row) {

                        if (!isset($result[$row['route']])) {
                            $result[$row['route']] = [];
                        }

                        $result[$row['route']] = $row;
                    }

                    return $result;
                };

                $metaRoutes = cache_data('json', 'MetaRoutes_' . $domain->getId() . '_' . $language->getId(), $callback, ['Seo_Model_MetaRoutes']);

                if (isset($metaRoutes[$route->getName()])) {
                    $meta = $metaRoutes[$route->getName()];
                }
            }

            $view->assign('metaRoute', $meta);

        });

        $manager->attach('*.Model.*.After.Delete', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof MetaInterface) {

                $model = new Meta();
                $model->useUiLanguage();
                $model->addWhere('target', $entity->getTarget());
                $model->addWhere('targetId', $entity->getTargetId());
                $meta = $model->getItem();

                if ($meta) {
                    $model->delete($meta);
                }
            }
        });

        $manager->attach('Crud.Form.Prepare', function (Message $message) {

            $entity = $message->entity;

            if ($entity instanceof MetaInterface) {

                $model = new Meta();
                $model->useCurrentLanguage();
                $model->addWhere('target', $entity->getTarget());
                $model->addWhere('targetId', $entity->getTargetId());
                $meta = $model->getItem();

                $titleSettings = [
                    'class' => 'form-control',
                    'value' => $meta ? $meta['title'] : '',
                    'attributes' => [
                        'data-lang-icon',
                    ]
                ];

                $message->form->addElement(new Element\Textarea('__metaTitle', $titleSettings));

                $descrSettings = [
                    'class' => 'form-control',
                    'value' => $meta ? $meta['description'] : '',
                    'attributes' => [
                        'data-lang-icon',
                    ]
                ];

                $message->form->addElement(new Element\Textarea('__metaDescription', $descrSettings));
            }
        });
    }

    public function getConfig()
    {
        return [];
    }
}
