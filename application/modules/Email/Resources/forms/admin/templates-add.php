<?php

return [
    'elements' => [
        'subject' => [
            'type' => 'text',
            'options' => [
                'label' => 'Относно',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'subject',
                ],
            ]
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Псевдоним',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    'exists' => [
                       'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Email\Model\Table\Templates::class,
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
            ]
        ],
        'testEmails' => [
            'type' =>'textarea',
            'options' => [
                'label' => 'Тестови мейли',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => [
                    'rows' => 10,
                ],
            ],
        ],
        'description' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Съдържание',
                'labelClass' => 'form-label',
                'class' => 'tinymce form-control',
                'required' => 1,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'description',
                    'id' => 'descriptionElement',
                    'rows' => 20,
                ],
            ],
        ],
        'descriptionIsEditor' => [
            'type' => 'checkbox',
            'options' => [
                'class' => 'toggleTinymce',
                'value' => 1,
                'attributes' => [
                    'data-content-id' => 'descriptionElement',
                ],
            ],
        ],
        'active' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Активност',
            ],
        ],
        'btnSend'  => ['type' => 'submit', 'options' => ['value' => 'Тествай', 'class' => 'btn btn-primary']],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];