<?php

namespace Seo\Model\Table;

use Micro\Database\Table\TableAbstract;

class MetaRoutesLang extends TableAbstract
{
    protected $_name = 'MetaRoutesLang';

    protected $_referenceMap = [
        'MetaRoute' => [
            'columns'       => 'metaId',
            'refTableClass' => MetaRoutes::class,
            'refColumns'    => 'id',
        ]
    ];
}