<?php

return [
    'elements' => [
        'right' => [
            'type' => 'text',
            'options' => [
                'label' => 'Право',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Наименование',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];