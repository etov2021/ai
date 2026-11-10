<?php

namespace Navigation\Helper;

use Micro\Database\Expr;
use Micro\Container\SharedContainer;
use Micro\Helper\Tree\TreeInterface;
use Micro\Helper\Tree\TreeTrait;

class Tree implements TreeInterface
{
    use TreeTrait;

    protected $menu;
    protected $domainId;

    public function __construct($menu, $domainId = null)
    {
        if ($domainId === null) {
            try {
                $domain = SharedContainer::getInstance()->get('domain');
                if ($domain) {
                    $domainId = $domain->getId();
                }
            } catch (\Exception $e) {

            }
        }

        $this->menu = $menu;
        $this->domainId = (int) $domainId;
    }

    public function loadTree($active = 1)
    {
        $container = SharedContainer::getInstance();

        $db = $container->get('db');

        if (!$db) {
            return [];
        }

        $select = $db->select()
                     ->from('MenuItems')
                     ->joinInner('Menu', 'Menu.id = MenuItems.menuId', [])
                     ->where('Menu.alias = ?', $this->menu)
                     ->columns([new Expr('1 as level')])
                     ->order('MenuItems.order ASC');

        if ($this->domainId) {
            $select->where('Menu.domainId = ?', $this->domainId);
        }

        if ($container->has('language')) {
            $language = $container->get('language');
            $select->joinInner('MenuItemsLang', 'MenuItemsLang.itemId = MenuItems.id AND MenuItemsLang.languageId = ' . $language->getId());
        }

        if ($active !== \null) {
            $select->where('MenuItems.active = ?', ($active ? 1 : 0));
            $select->where('Menu.active = ?', ($active ? 1 : 0));
        }

        return $db->fetchAll($select);
    }
}