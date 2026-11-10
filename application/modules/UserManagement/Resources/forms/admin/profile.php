<?php

return [
    'elements' => [
        'email' => [
            'type' => 'text',
            'options' => [
                'label' => 'Електронна поща',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => ['Email'],
            ]
        ],
        'password' => [
            'type' => 'password',
            'options' => [
                'label' => 'Парола',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'validators' => [
                    [
                        'validator' => 'Identical',
                        'options'   => [
                            'field' => 'repassword',
                            'error' => 'Паролите не съвпадат',
                        ]
                    ],
                ],
            ]
        ],
        'repassword' => [
            'type' => 'password',
            'options' => [
                'label' => 'Повтори паролата',
                'labelClass' => 'form-label',
                'class' => 'form-control',
            ],
        ],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
    ]
];