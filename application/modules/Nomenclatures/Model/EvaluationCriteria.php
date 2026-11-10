<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class EvaluationCriteria extends TranslationDatabaseAbstract
{
    protected $entity = Entity\EvaluationCriteria::class;

    protected $table = Table\EvaluationCriteria::class;
}