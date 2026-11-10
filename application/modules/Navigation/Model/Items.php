<?php

namespace Navigation\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Cache;

class Items extends TranslationDatabaseAbstract
{
    protected $table = Table\Items::class;

    protected $entity = Entity\Item::class;

    public function removeCache()
    {
        if (\config('navigation.remove_cache', true)) {
            foreach (Cache\Cache::loadAll() as $cache) {
                $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, [$this->getCacheId()]);
            }
        }

        return $this;
    }

    public function clone($item, $menuId, $languageId, $parentId = null)
    {
        $oldId = $item['id'];

        $item->setId(null);
        $item->setParentId($parentId);
        $item->setMenuId($menuId);
        $item->setLanguageId($languageId);

        if ($item->getParentId()) {
            $and = ' AND parentId = ' . (int) $item->getParentId();
        } else {
            $and = ' AND parentId IS NULL';
        }

        $item->setOrder($this->getTable()->getAdapter()->fetchOne('SELECT IFNULL(MAX(`order`), 0) + 1 FROM MenuItems WHERE menuId = ' . (int) $menuId . $and));

        $this->save($item);

        foreach ($this->getChildren($oldId) as $child) {
            $this->clone($child, $menuId, $languageId, $item['id']);
        }
    }

    protected function getChildren($itemId)
    {
        return $this->resetWhere()->addWhere('parentId', $itemId)->getItems();
    }
}