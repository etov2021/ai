<?php

return [
    'elements' => [
        'code' => [
            'type' => 'text',
            'options' => [
                'label' => 'Код (латиница, след запис не се променя)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 40, 'autocomplete' => 'off'],
            ],
        ],
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Име',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 100],
            ],
        ],
        'description' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Описание',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['rows' => 2, 'maxlength' => 1000],
            ],
        ],
        'definition' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Стъпки (JSON)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 18, 'style' => 'font-family: monospace'],
            ],
        ],
        'active' => ['type' => 'checkbox', 'options' => ['label' => 'Активен']],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack' => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ],
];
