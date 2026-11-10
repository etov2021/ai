<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProgramsLang extends TableAbstract
{
    protected $_name = 'NomStatusProgramsLang';

    protected $_referenceMap = [
        'StatusProgram' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusPrograms::class,
            'refColumns'    => 'id',
        ]
    ];
}