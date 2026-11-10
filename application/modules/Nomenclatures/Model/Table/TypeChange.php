<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class TypeChange extends TableAbstract
{
    protected $_name = 'NomTypeChange';

    protected $_dependentTables = [TypeChangeLang::class];
}