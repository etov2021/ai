<?php

return [
    'elements' => [
        'email' => [
            'type' => 'text',
            'options' => [
                'label' => 'Електронна поща',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'validators' => ['Email'],
                'belongsTo' => 'filters',
            ],
        ],
        'names' => [
            'type' => 'text',
            'options' => [
                'label' => 'Имена',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'belongsTo' => 'filters',
            ],
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];