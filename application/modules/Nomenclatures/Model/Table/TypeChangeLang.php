<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class TypeChangeLang extends TableAbstract
{
    protected $_name = 'NomTypeChangeLang';

    protected $_referenceMap = [
        'TypeChange' => [
            'columns'       => 'changeId',
            'refTableClass' => TypeChange::class,
            'refColumns'    => 'id',
        ]
    ];
}