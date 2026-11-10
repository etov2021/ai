<?php

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Наименование',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ],
        ],
        'abbr' => [
            'type' => 'text',
            'options' => [
                'label' => 'Абревиатура',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ],
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];