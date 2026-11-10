<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProcurementsLang extends TableAbstract
{
    protected $_name = 'NomStatusProcurementsLang';

    protected $_referenceMap = [
        'StatusProcurements' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusProcurements::class,
            'refColumns'    => 'id',
        ]
    ];
}