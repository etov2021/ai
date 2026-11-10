<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class EvaluationCriteria extends TableAbstract
{
    protected $_name = 'NomEvaluationCriteria';

    protected $_dependentTables = [EvaluationCriteriaLang::class];
}