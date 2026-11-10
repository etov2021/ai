<?php

namespace Navigation\Model\Table;

use Micro\Database\Table\TableAbstract;
use Navigation\Model;

class Items extends TableAbstract
{
    protected $_name = 'MenuItems';

    protected $_dependentTables = [ItemsLang::class];

    public function updateTree(array $tree)
    {
        $caseOrder  = '';
        $caseParent = '';

        $this->_buildWhen($tree, \null, $caseOrder, $caseParent);

        if ($caseOrder && $caseParent) {

            $sql = 'UPDATE MenuItems SET `order` = CASE %s ELSE `order` END, `parentId` = CASE %s ELSE `parentId` END';

            $this->getAdapter()->query(sprintf($sql, trim($caseOrder), trim($caseParent)));

            (new Model\Items())->removeCache();
        }
    }

    protected function _buildWhen(array $tree, $parent, &$caseOrder, &$caseParent)
    {
        foreach ($tree as $k => $item) {

            $caseOrder .= ' WHEN id = ' . (int) $item['id'] . ' THEN ' . ($k + 1);
            $caseParent .= ' WHEN id = ' . (int) $item['id'] . ' THEN ' . ($parent === \null ? 'NULL' : $parent);

            if (!empty($item['children'])) {
                $this->_buildWhen($item['children'], $item['id'], $caseOrder, $caseParent);
            }
        }
    }
}