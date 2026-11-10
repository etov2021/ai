<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class ClientsLang extends TableAbstract
{
    protected $_name = 'NomClientsLang';

    protected $_referenceMap = [
        'StatusClient' => [
            'columns'       => 'clientId',
            'refTableClass' => Clients::class,
            'refColumns'    => 'id',
        ]
    ];
}