<?php

$languages = (new Translations\Model\Languages())->fetchCachedPairs(null, null, ['id' => 'asc']);

return [
    'elements' => [
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Ключ',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'languageId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Език',
                'labelClass' => 'form-label',
                'class' => 'form-control select2',
                'multiOptions' => $languages,
                'belongsTo' => 'filters',
            ]
        ],
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Превод',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];