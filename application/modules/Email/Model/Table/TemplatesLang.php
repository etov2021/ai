<?php

namespace Email\Model\Table;

use Micro\Database\Table\TableAbstract;

class TemplatesLang extends TableAbstract
{
    protected $_name = 'EmailTemplatesLang';

    protected $_referenceMap = [
        'Template' => [
            'columns'       => 'templateId',
            'refTableClass' => Templates::class,
            'refColumns'    => 'id',
        ]
    ];
}