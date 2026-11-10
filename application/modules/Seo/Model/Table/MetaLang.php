<?php

namespace Seo\Model\Table;

use Micro\Database\Table\TableAbstract;

class MetaLang extends TableAbstract
{
    protected $_name = 'MetaLang';

    protected $_referenceMap = [
        'Meta' => [
            'columns'       => 'metaId',
            'refTableClass' => Meta::class,
            'refColumns'    => 'id',
        ]
    ];
}