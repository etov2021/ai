<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class SummaryLang extends TableAbstract
{
    protected $_name = 'NomSummaryLang';

    protected $_referenceMap = [
        'Summary' => [
            'columns'       => 'summaryId',
            'refTableClass' => Summary::class,
            'refColumns'    => 'id',
        ]
    ];
}