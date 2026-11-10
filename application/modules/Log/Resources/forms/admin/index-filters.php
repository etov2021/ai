<?php

$treeHelper = new Categories\Helper\Tree('news');

return [
    'elements' => [
        'creator' => [
            'type' => 'text',
            'options' => [
                'label' => 'Потребител',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'module' => [
            'type' => 'text',
            'options' => [
                'label' => 'Модул',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'controller' => [
            'type' => 'text',
            'options' => [
                'label' => 'Контролер',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'action' => [
            'type' => 'text',
            'options' => [
                'label' => 'Действие',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'message' => [
            'type' => 'text',
            'options' => [
                'label' => 'Съобщение',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'startDate' => [
            'type' => 'datetime',
            'options' => [
                'label' => 'Начална дата',
                'labelClass' => 'form-label',
                'class' => 'startDate form-control',
                'format' => 'd.m.Y',
                'belongsTo' => 'filters',
                'attributes' => [
                    'autocomplete' => 'off',
                ],
            ],
        ],
        'endDate' => [
            'type' => 'datetime',
            'options' => [
                'label' => 'Крайна дата',
                'labelClass' => 'form-label',
                'class' => 'endDate form-control',
                'format' => 'd.m.Y',
                'belongsTo' => 'filters',
                'attributes' => [
                    'autocomplete' => 'off',
                ],
            ],
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];