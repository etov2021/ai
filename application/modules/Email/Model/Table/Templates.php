<?php

namespace Email\Model\Table;

use Micro\Database\Table\TableAbstract;

class Templates extends TableAbstract
{
    protected $_name = 'EmailTemplates';

    protected $_dependentTables = [TemplatesLang::class];
}