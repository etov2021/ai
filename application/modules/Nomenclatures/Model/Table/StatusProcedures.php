<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProcedures extends TableAbstract
{
    protected $_name = 'NomStatusProcedures';

    protected $_dependentTables = [StatusProceduresLang::class];
}