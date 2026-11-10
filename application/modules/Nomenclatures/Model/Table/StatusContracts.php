<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusContracts extends TableAbstract
{
    protected $_name = 'NomStatusContracts';

    protected $_dependentTables = [StatusContractsLang::class];
}