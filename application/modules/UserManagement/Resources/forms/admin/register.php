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
                'validators' => [
                    'Email',
                    'fieldExists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'error' => 'Потребителят съществува в базата данни',
                            'table' => UserManagement\Model\Table\Users::class,
                            'field' => 'username',
                        ],
                    ],
                ],
            ],
        ],
        'firstName' => [
            'type'  => 'text',
            'options' => [
                'label' => 'Име',
                'required' => 1,
            ],
        ],
        'middleName' => [
            'type'  => 'text',
            'options' => [
                'label' => 'Презиме',
                'required' => 0,
            ],
        ],
        'lastName' => [
            'type'  => 'text',
            'options' => [
                'label' => 'Фамилия',
                'required' => 1,
            ],
        ],
        'password' => [
            'type' => 'password',
            'options' => [
                'label' => 'Парола',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
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
                'required' => 1,
            ],
        ],
        'btnRegister' => [
            'type'  => 'submit',
            'options' => [
                'value' => 'Регистрация',
                'class' => 'btn btn-primary',
            ],
        ],
    ],
];
