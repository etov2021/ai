<?php

namespace Categories\Model\Table;

use Micro\Database\Table\TableAbstract;

class CategoriesLang extends TableAbstract
{
    protected $_name = 'CategoriesLang';

    protected $_referenceMap = [
        'News' => [
            'columns'       => 'categoryId',
            'refTableClass' => Categories::class,
            'refColumns'    => 'id',
        ]
    ];
}