<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class Clients extends TableAbstract
{
    protected $_name = 'NomClients';

    protected $_dependentTables = [ClientsLang::class];
}