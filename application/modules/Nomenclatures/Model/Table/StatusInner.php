<?php

namespace Nomenclatures\Model\Table;

use Micro\Database\Table\TableAbstract;

class StatusInner extends TableAbstract
{
    protected $_name = 'NomStatusInner';

    protected $_dependentTables = [StatusInnerLang::class];
}