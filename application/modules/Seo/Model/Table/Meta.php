<?php

namespace Seo\Model\Table;

use Micro\Database\Table\TableAbstract;

class Meta extends TableAbstract
{
    protected $_name = 'Meta';

    protected $_dependentTables = [MetaLang::class];
}