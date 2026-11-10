<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProjects extends TableAbstract
{
    protected $_name = 'NomStatusProjects';

    protected $_dependentTables = [StatusProjectsLang::class];
}