<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class EvaluationCriteriaLang extends TableAbstract
{
    protected $_name = 'NomEvaluationCriteriaLang';

    protected $_referenceMap = [
        'EvaluationCriteria' => [
            'columns'       => 'criteriaId',
            'refTableClass' => EvaluationCriteria::class,
            'refColumns'    => 'id',
        ]
    ];
}