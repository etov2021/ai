<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProjectsLang extends TableAbstract
{
    protected $_name = 'NomStatusProjectsLang';

    protected $_referenceMap = [
        'StatusProject' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusProjects::class,
            'refColumns'    => 'id',
        ]
    ];
}