<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProceduresLang extends TableAbstract
{
    protected $_name = 'NomStatusProceduresLang';

    protected $_referenceMap = [
        'StatusProcedure' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusProcedures::class,
            'refColumns'    => 'id',
        ]
    ];
}