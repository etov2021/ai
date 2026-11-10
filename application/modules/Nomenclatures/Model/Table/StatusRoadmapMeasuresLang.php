<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusRoadmapMeasuresLang extends TableAbstract
{
    protected $_name = 'NomStatusRoadmapMeasuresLang';

    protected $_referenceMap = [
        'StatusRoadmapMeasure' => [
            'columns'       => 'statusId',
            'refTableClass' => StatusRoadmapMeasures::class,
            'refColumns'    => 'id',
        ]
    ];
}