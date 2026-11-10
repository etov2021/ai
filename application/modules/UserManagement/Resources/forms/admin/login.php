<?php

return [
    'elements' => [
        'language' => [
            'type'  => 'select',
            'options' => [
                'label' => 'Език',
                'class' => 'form-control',
            ],
        ],
        'username' => [
            'type'  => 'text',
            'options' => [
                'label' => 'Потребителско име',
                'labelClass' => 'fw-semibold',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'password' => [
            'type'  => 'password',
            'options' => [
                'label' => 'Парола',
                'labelClass' => 'fw-semibold',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'btnLogin' => [
            'type'  => 'submit',
            'options' => [
                'value' => 'Вход',
                'class' => 'btn btn-primary w-md waves-effect waves-light',
            ],
        ],
    ],
];
