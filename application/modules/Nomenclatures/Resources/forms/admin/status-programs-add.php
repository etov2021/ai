<?php

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Наименование',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ]
        ],
        'color' => [
            'type' => 'text',
            'options' => [
                'label' => 'Цвят',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => [
                    'id' => 'color',
                    'data-jscolor' => '',
                ],
            ]
        ],
        'active' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Активност',
                'class' => 'custom-control-input',
                'attributes' => [
                    'id' => 'active',
                ],
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];