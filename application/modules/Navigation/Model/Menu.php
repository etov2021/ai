<?php

namespace Navigation\Model;

use Micro\Model\DatabaseAbstract;
use Micro\Cache;

class Menu extends DatabaseAbstract
{
    protected $table = Table\Menu::class;
    protected $entity = Entity\Menu::class;

    public function removeCache()
    {
        if (\config('navigation.remove_cache', true)) {
            foreach (Cache\Cache::loadAll() as $cache) {
                $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, [$this->getCacheId()]);
                $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, ['Navigation_Model_Items']);
            }
        }

        return $this;
    }

    public function removeCacheByMenu($id)
    {
        $menu = $this->find((int) $id);

        if ($menu) {
            $domain = app('domain', true);
            $language = app('language', true);
            $cacheId = 'Menu_' . $menu->getAlias() . '_' . ($domain ? $domain->getId() : 0) . '_' . ($language ? $language->getId() : 0);
            $cacheId = Cache\Cache::normalizeKey($cacheId);
            foreach (Cache\Cache::loadAll() as $cache) {
                $cache->remove($cacheId);
            }
        }
    }
}