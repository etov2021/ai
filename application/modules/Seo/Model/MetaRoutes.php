<?php

namespace Seo\Model;

use Micro\Model\TranslationDatabaseAbstract;

class MetaRoutes extends TranslationDatabaseAbstract
{
    protected $entity = Entity\MetaRoute::class;

    protected $table = Table\MetaRoutes::class;

    /**
     * @param string $route
     * @param int $domainId
     * @return EntityInterface
     */
    public function getAlwaysMetaItem($route, $domainId = null)
    {
        if ($domainId === null) {
            $domainId = defined('DOMAIN') ? DOMAIN : 0;
        }

        $this->addWhere('route', $route);
        $this->addWhere('domainId', $domainId);

        $item = $this->getItem();

        if ($item === \null) {
            $item = $this->createEntity([
                'route' => $route,
                'domainId' => $domainId
            ]);
        }

        return $item;
    }
}