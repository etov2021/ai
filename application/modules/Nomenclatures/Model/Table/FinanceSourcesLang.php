<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class FinanceSourcesLang extends TableAbstract
{
    protected $_name = 'NomFinanceSourcesLang';

    protected $_referenceMap = [
        'FinanceSource' => [
            'columns'       => 'financeSourceId',
            'refTableClass' => FinanceSources::class,
            'refColumns'    => 'id',
        ]
    ];
}