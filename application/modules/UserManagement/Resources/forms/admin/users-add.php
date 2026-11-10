<?php

use UserManagement\Model\Groups;
use UserManagement\Model\Rights;
use UserManagement\Helper\GroupsTree;

$groupsTreeHelper = new GroupsTree();

$groupsTreeHelper->setOnSelect(function ($select) {
    $select->where('alias <> ?', 'guest');
});

$groups = $groupsTreeHelper->toSelectOptions($groupsTreeHelper->getTree());

return [
    'elements' => [
        'username' => [
            'type' => 'text',
            'options' => [
                'label' => 'Потребител',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    'fieldExists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'error' => 'Потребителят съществува в базата данни',
                            'table' => UserManagement\Model\Table\Users::class,
                            'field' => 'username',
                            'callback' => function ($select) {
                                $domain = app('domain', true);
                                if ($domain) {
                                    $select->where('domainId = ?', $domain->getId());
                                }
                            },
                        ],
                    ],
                ],
            ]
        ],
        'email' => [
            'type' => 'text',
            'options' => [
                'label' => 'Електронна поща',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    'Email',
                    'fieldExists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'error' => 'Електронната поща съществува в базата данни',
                            'table' => UserManagement\Model\Table\Users::class,
                            'field' => 'email',
                            'callback' => function ($select) {
                                $domain = app('domain', true);
                                if ($domain) {
                                    $select->where('domainId = ?', $domain->getId());
                                }
                            },
                        ],
                    ],
                ],
            ]
        ],
        'firstName' => [
            'type' => 'text',
            'options' => [
                'label' => 'Име',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'middleName' => [
            'type' => 'text',
            'options' => [
                'label' => 'Презиме',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
            ],
        ],
        'lastName' => [
            'type' => 'text',
            'options' => [
                'label' => 'Фамилия',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'groupId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Група',
                'labelClass' => 'form-label',
                'class' => 'form-control select2',
                'isArray' => 1,
                'required' => 1,
                'emptyOption' => 'Избери',
                'multiOptions' => $groups,
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
        'password' => [
            'type' => 'password',
            'options' => [
                'label' => 'Парола',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    [
                        'validator' => 'Identical',
                        'options'   => [
                            'field' => 'repassword',
                            'error' => 'Паролите не съвпадат',
                        ]
                    ],
                ],
            ]
        ],
        'repassword' => [
            'type' => 'password',
            'options' => [
                'label' => 'Повтори паролата',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'active' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Активност',
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ]
];