<?php

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Име на категория',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'name',
                ],
            ],
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Служебно име',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'validators' => [
                    'exists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Categories\Model\Table\Categories::class,
                            'field' => 'alias',
                        ],
                    ],
                ],
            ],
        ],
        'permalink' => [
            'type' => 'text',
            'options' => [
                'label' => 'Псевдоним',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'validators' => [
                    'regex' => [
                        'validator' => 'Regex',
                        'options' => [
                            'pattern' => '~^[a-z0-9\-\_]+$~'
                        ],
                    ],
                ],
            ],
        ],
        'parentId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Родител',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'emptyOption' => 'Избери',
            ]
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