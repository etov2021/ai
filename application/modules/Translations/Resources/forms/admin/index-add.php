<?php

use Domains\Model\Domains;
use Micro\Database\Expr;

return [
    'elements' => [
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Ключ',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    'exists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Translations\Model\Table\Translations::class,
                            'field' => 'alias',
                            'callback' => function ($select, $context) {
                                $where = $select->getPart('where');
                                $where[0] = preg_replace('~\(alias~', '(BINARY alias', $where[0]);
                                $select->setPart('where', $where);
                                if ($context['domainId']) {
                                    $select->where('domainId = ?', $context['domainId']);
                                } else {
                                    $select->where(new Expr('domainId IS NULL'));
                                }
                            },
                        ],
                    ],
                ],
            ],
        ],
        'domainId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Domain',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'emptyOption' => 'Select domain',
                'multiOptions' => (new Domains())->fetchCachedPairs(null, null, ['id' => 'ASC']),
            ],
        ],
        'active' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Активност',
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];