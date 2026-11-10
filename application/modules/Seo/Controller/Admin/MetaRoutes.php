<?php

namespace Seo\Controller\Admin;

use Micro\Http\Response\JsonResponse;
use Micro\Container\SharedContainer;
use Seo\Model\MetaRoutes as ModelMetaRoutes;
use Micro\Application\Controller\Admin;

class MetaRoutes extends Admin
{
    protected $model = ModelMetaRoutes::class;

    public function indexAction()
    {
        $route = $this->request->getParam('route', '');
        $metaLanguage = $this->request->getParam('meta_language', '');

        $languages = $this->getValidLanguages();
        $language  = $metaLanguage && isset($languages[$metaLanguage]) ? $languages[$metaLanguage] : $this->getUiLanguage();

        $meta = null;

        if (!empty($route) ) {
            $model = $this->getModel();
            $model->setLanguage($language);
            $meta = $model->getAlwaysMetaItem($route, DOMAIN);
        }

        return [
            'route'     => $route,
            'language'  => $language,
            'meta'      => $meta,
            'languages' => $languages,
        ];
    }

    public function ajaxUpdateAction()
    {
        $route = $this->request->getParam('route', '');
        $metaLanguage = $this->request->getParam('meta_language', '');

        $languages = $this->getValidLanguages();
        $language  = $metaLanguage && isset($languages[$metaLanguage]) ? $languages[$metaLanguage] : $this->getUiLanguage();

        if ($route) {

            $title = $this->request->getParam('title', '');
            $description = $this->request->getParam('description', '');

            $model = $this->getModel();
            $model->setLanguage($language);
            $item = $model->getAlwaysMetaItem($route, DOMAIN);

            if (empty($title) && empty($description) && $item['id']) {
                $model->delete($item);
            } else {
                $item->setFromArray(['title' => $title, 'description' => $description]);
                $model->save($item);
            }
        }

        return new JsonResponse(['success' => 1]);
    }

    protected function getValidLanguages()
    {
        $container = SharedContainer::getInstance();

        $domainLanguages = $container->get('domain')->getLanguages();

        if ($domainLanguages) {
            $domainLanguages = explode(',', $domainLanguages);
        } else {
            $domainLanguages = [];
        }

        $languages = [];

        foreach ($container->get('languages') as $lang) {
            if (!empty($domainLanguages) && !in_array($lang['id'], $domainLanguages)) {
                continue;
            }
            $languages[$lang['code']] = $lang;
        }

        return $languages;
    }
}