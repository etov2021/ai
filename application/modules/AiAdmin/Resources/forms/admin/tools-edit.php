<?php

return [
    'elements' => [
        'code' => [
            'type' => 'text',
            'options' => [
                'label' => 'Код = име на функцията за модела (латиница, след запис не се променя)',
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
        'type' => [
            'type' => 'select',
            'options' => [
                'label' => 'Тип',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'description' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Описание (вижда се от модела - кога да ползва инструмента)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 3, 'maxlength' => 1000],
            ],
        ],
        'parameters' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Параметри (JSON Schema от тип object)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 10, 'style' => 'font-family: monospace'],
            ],
        ],
        'config' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Конфигурация (за HTTP: method, url с {параметър}, allowedHosts, maxResultChars, timeoutSeconds)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['rows' => 8, 'style' => 'font-family: monospace'],
            ],
        ],
        'active' => ['type' => 'checkbox', 'options' => ['label' => 'Активен']],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack' => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ],
];
