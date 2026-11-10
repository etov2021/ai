<?php

namespace Translations\Model\Table;

use Micro\Database\Table\TableAbstract;

class Translations extends TableAbstract
{
    protected $_name = 'Translations';

    protected $_dependentTables = [TranslationsLang::class];
}