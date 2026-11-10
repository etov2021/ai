<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class FinanceSources extends TableAbstract
{
    protected $_name = 'NomFinanceSources';

    protected $_dependentTables = [FinanceSourcesLang::class];
}