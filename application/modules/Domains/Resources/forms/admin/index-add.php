<?php

$languages = (new Translations\Model\Languages())->fetchCachedPairs(null, null, ['id' => 'asc']);

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Наименование',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'addresses' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Адреси',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => [
                    'rows' => 10,
                ],
            ],
        ],
        'countryCode' => [
            'type' => 'text',
            'options' => [
                'label' => 'Country code',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'protocol' => [
            'type' => 'text',
            'options' => [
                'label' => 'Протокол',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'template' => [
            'type' => 'text',
            'options' => [
                'label' => 'Шаблон',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'header' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Header html',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => [
                    'rows' => 10,
                ],
            ],
        ],
        'footer' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Footer html',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => [
                    'rows' => 10,
                ],
            ],
        ],
        'defaultLanguage' => [
            'type' => 'select',
            'options' => [
                'label' => 'Базов език',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'required' => 1,
                'emptyOption' => 'Избери',
                'multiOptions' => $languages,
            ],
        ],
        'languages' => [
            'type' => 'select',
            'options' => [
                'label' => 'Езици за домейна',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'required' => 1,
                'isArray' => 1,
                'attributes' => [
                    'data-placeholder' => 'Изберете език',
                ],
                'multiOptions' => $languages,
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];