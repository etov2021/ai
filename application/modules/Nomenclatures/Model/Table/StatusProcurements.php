<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusProcurements extends TableAbstract
{
    protected $_name = 'NomStatusProcurements';

    protected $_dependentTables = [StatusProcurementsLang::class]; 
}