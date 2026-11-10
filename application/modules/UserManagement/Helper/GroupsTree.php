<?php

namespace UserManagement\Helper;

use Micro\Database\Expr;
use Micro\Container\SharedContainer;
use Micro\Helper\Tree\TreeInterface;
use Micro\Helper\Tree\TreeTrait;

class GroupsTree implements TreeInterface
{
    use TreeTrait;

    protected $onSelect;

    public function loadTree($active = \null)
    {
        $container = SharedContainer::getInstance();

        $db = $container->get('db');

        if (!$db) {
            return [];
        }

        $select = $db->select()
                     ->from('UsersGroups')
                     ->columns([new Expr('1 as level')])
                     ->order('UsersGroups.id DESC');

        if ($active !== \null) {
            $select->where('Categories.active = ?', ($active ? 1 : 0));
        }

        if ($this->onSelect !== \null && $this->onSelect instanceof \Closure) {
            call_user_func($this->onSelect, $select);
        }

        return $db->fetchAll($select);
    }

    public function setOnSelect(\Closure $onSelect)
    {
        $this->onSelect = $onSelect;

        return $this;
    }

    public function getPartialTree($id, $active = \null)
    {
        $items = $this->loadTree($active);

        return $this->getChildren($id, $items);
    }
}