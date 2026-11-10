<?php

namespace Seo\Model\Table;

use Micro\Database\Table\TableAbstract;

class MetaRoutes extends TableAbstract
{
    protected $_name = 'MetaRoutes';

    protected $_dependentTables = [MetaRoutesLang::class];
}