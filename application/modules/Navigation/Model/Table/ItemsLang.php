<?php

namespace Navigation\Model\Table;

use Micro\Database\Table\TableAbstract;

class ItemsLang extends TableAbstract
{
    protected $_name = 'MenuItemsLang';

    protected $_referenceMap = [
        'Item' => [
            'columns'       => 'itemId',
            'refTableClass' => Items::class,
            'refColumns'    => 'id',
        ]
    ];
}