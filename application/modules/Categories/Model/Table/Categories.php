<?php

namespace Categories\Model\Table;

use Micro\Database\Table\TableAbstract;
use Categories\Model\Categories as CategoriesModel;

class Categories extends TableAbstract
{
    protected $_name = 'Categories';

    protected $_dependentTables = [CategoriesLang::class];

    public function updateTree(array $tree)
    {
        $caseOrder  = '';
        $caseParent = '';

        $this->_buildWhen($tree, \null, $caseOrder, $caseParent);

        if ($caseOrder && $caseParent) {

            $sql = 'UPDATE Categories SET `order` = CASE %s ELSE `order` END, `parentId` = CASE %s ELSE `parentId` END';

            $this->getAdapter()->query(sprintf($sql, trim($caseOrder), trim($caseParent)));

            (new CategoriesModel())->removeCache();
        }
    }

    protected function _buildWhen(array $tree, $parent = \null, &$caseOrder, &$caseParent)
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