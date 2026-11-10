<?php

return [
    'elements' => [
        'subject' => [
            'type' => 'text',
            'options' => [
                'label' => 'Относно',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Псевдоним',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];