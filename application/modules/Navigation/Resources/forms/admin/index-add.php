<?php

return array(
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Наименование',
                'labelClass' => 'form-label',
                'required' => 1,
                'class' => 'form-control',
            ],
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'required' => 1,
                'label' => 'Псевдоним',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'validators' => [
                    'exists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Navigation\Model\Table\Menu::class,
                            'field' => 'alias',
                            'callback' => function ($select) {
                                $domain = app('domain', true);
                                if ($domain) {
                                    $select->where('domainId = ?', $domain->getId());
                                }
                            }
                        ],
                    ],
                ]
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
);