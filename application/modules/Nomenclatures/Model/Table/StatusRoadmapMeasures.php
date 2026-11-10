<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusRoadmapMeasures extends TableAbstract
{
    protected $_name = 'NomStatusRoadmapMeasures';

    protected $_dependentTables = [StatusRoadmapMeasuresLang::class];
}