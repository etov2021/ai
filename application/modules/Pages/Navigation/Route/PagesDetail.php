<?php

namespace Pages\Navigation\Route;

use Pages\Model\Pages as ModelPages;
use Micro\Model\Entity\EntityInterface;
use Navigation\Model\Entity\Menu as EntityMenu;
use Micro\Router\Route;
use Micro\Database\Expr;

class PagesDetail
{
    public function resolve(array $routeData, Route $route, ?EntityMenu $menu = null)
    {
        $model = (new ModelPages())->useCurrentLanguage();
        $pairs = $model->fetchPairs([new Expr('name <> ""'), 'domainId' => DOMAIN], ['PagesLang.pageId', new Expr("CONCAT('(#', Pages.id, ') ', PagesLang.name)")], ['name' => 'asc']);

        return [
            [
                'label' => 'Изберете страница',
                'field' => 'page',
                'value' => $pairs
            ]
        ];
    }

    public function decode(array &$routeData, EntityInterface $item)
    {
        $model = (new ModelPages())->useCurrentLanguage()
                                   ->addWhere('id', (int) $routeData['page']);

        $item = $model->getItem();

        if ($item) {
            $routeData['alias'] = $item->getAlias();
        }

        return;
    }
}