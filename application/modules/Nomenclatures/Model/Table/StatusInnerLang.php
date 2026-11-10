<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusInnerLang extends TableAbstract
{
    protected $_name = 'NomStatusInnerLang';

    protected $_referenceMap = [
        'StatusInner' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusInner::class,
            'refColumns'    => 'id',
        ]
    ];
}