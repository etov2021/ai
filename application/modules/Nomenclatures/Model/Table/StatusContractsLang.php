<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusContractsLang extends TableAbstract
{
    protected $_name = 'NomStatusContractsLang';

    protected $_referenceMap = [
        'StatusContract' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusContracts::class,
            'refColumns'    => 'id',
        ]
    ];
}