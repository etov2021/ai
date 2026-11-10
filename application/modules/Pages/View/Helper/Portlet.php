<?php

namespace Pages\View\Helper;

use Pages\Model\Pages;
use Micro\View\View;
use Micro\View\Helper\HelperAbstract;

class Portlet extends HelperAbstract
{
    protected $item;
    protected $viewScript;

    public function __invoke($alias, $viewScript = 'clean', $language = \null)
    {
        if (empty($alias)) {
            return '';
        }

        $model = new Pages();

        if (empty($language)) {
            $model->useUiLanguage();
        } else {
            $model->useLanguageByCode($language);
        }

        $domainId = defined('DOMAIN') ? DOMAIN : 0;

        $model->addWhere('alias', $alias);
        $model->addWhere('active', 1);
        $model->addWhere('languageId', $model->getLanguage()->getId());
        $model->addWhere('domainId', $domainId);

        $cacheId = md5($model->getSelect()->__toString());

        $item = cache_data('json', 'Portlet_' . $domainId . '_' . $model->getLanguage()->getId() . '_' . $cacheId, function () use ($model, $alias, $language) {

            $item = $model->getItem();

            if ($item) {
                return $item->toArray();
            }

            return [];

        }, [$model->getCacheId()]);

        $this->item = $item;
        $this->viewScript = $viewScript;

        return $this;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function __toString()
    {
        try {
            if ($this->item) {
                $view = new View(
                    $this->viewScript,
                    ['item' => $this->item],
                    [__DIR__ . '/Resources/views']
                );
                return $view->render();
            } else {
                return '';
            }
        } catch (\Exception $e) {
            if (env('development')) {
                return $e->getMessage();
            }
        }

        return '';
    }
}