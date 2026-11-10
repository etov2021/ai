<?php

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Език',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ]
        ],
        'code' => [
            'type' => 'text',
            'options' => [
                'label' => 'Код',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ]
        ],
        'locale' => [
            'type' => 'text',
            'options' => [
                'label' => 'Locale',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
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