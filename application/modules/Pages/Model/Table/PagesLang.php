<?php

namespace Pages\Model\Table;

use Micro\Database\Table\TableAbstract;

class PagesLang extends TableAbstract
{
    protected $_name = 'PagesLang';

    protected $_referenceMap = [
        'Page' => [
            'columns'       => 'pageId',
            'refTableClass' => Pages::class,
            'refColumns'    => 'id',
        ]
    ];
}