<?php

namespace Pages\Model\Table;

use Micro\Database\Table\TableAbstract;

class Pages extends TableAbstract
{
    protected $_name = 'Pages';

    protected $_dependentTables = [PagesLang::class];
}