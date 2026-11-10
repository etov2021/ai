<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class Summary extends TableAbstract
{
    protected $_name = 'NomSummary';

    protected $_dependentTables = [SummaryLang::class];
}