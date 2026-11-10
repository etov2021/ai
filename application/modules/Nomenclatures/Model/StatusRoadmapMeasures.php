<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusRoadmapMeasures extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusRoadmapMeasure::class;

    protected $table = Table\StatusRoadmapMeasures::class;
}