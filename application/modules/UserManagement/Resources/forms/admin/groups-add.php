<?php

use UserManagement\Model\Rights;

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Група',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1
            ]
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'Псевдоним',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ]
        ],
        'parentId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Родител',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'emptyOption'  => 'Без родител',
                'multiOptions' => [],
            ],
        ],
        'customRights' => [
            'type' => 'select',
            'options' => [
                'label' => 'Предефинирани права',
                'labelClass' => 'form-label',
                'class' => 'form-control select2',
                'isArray' => 1,
                'attributes' => [
                    'data-placeholder' => 'Изберете права',
                ],
                'multiOptions' => (new Rights())->fetchPairs(null, [expr('UsersGroupsRights.right'), expr('UsersGroupsRights.name')]),
            ],
        ],
        'fileManagerPersonalFolder' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Лична папка в файл мениджъра',
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];
