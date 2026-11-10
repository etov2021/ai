<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusPrograms extends TableAbstract
{
    protected $_name = 'NomStatusPrograms';

    protected $_dependentTables = [StatusProgramsLang::class];
}