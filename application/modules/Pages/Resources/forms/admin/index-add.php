<?php

use Pages\Model\Entity\Page;

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Заглавие на страницата',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'name',
                ],
            ]
        ],
        'type' => [
            'type' => 'select',
            'options' => [
                'label' => 'Page type',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'required' => 1,
                'emptyOption' => 'Select',
                'multiOptions' => Page::$TYPES,
                'translateValue' => true,
            ]
        ],
        'alias' => [
            'type' => 'text',
            'options' => [
                'label' => 'SEO friendly URL',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 1,
                'validators' => [
                    'exists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Pages\Model\Table\Pages::class,
                            'field' => 'alias',
                            'callback' => function ($select) {
                                $domain = app('domain', true);
                                if ($domain) {
                                    $select->where('domainId = ?', $domain->getId());
                                }
                            }
                        ],
                    ],
                ]
            ]
        ],
        'link' => [
            'type' => 'text',
            'options' => [
                'label' => 'Външен линк',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                 'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'link',
                ],
            ]
        ],
        'content' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Съдържание',
                'labelClass' => 'control-label',
                'class' => 'tinymce form-control',
                'required' => 0,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'content',
                    'id' => 'contentElement',
                    'rows' => 20,
                ],
            ]
        ],
        'contentIsEditor' => [
            'type' => 'checkbox',
            'options' => [
                'class' => 'toggleTinymce',
                'value' => 1,
                'attributes' => [
                    'data-content-id' => 'contentElement',
                ],
            ],
        ],
        'contentMobile' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Съдържание за мобилна версия',
                'labelClass' => 'control-label',
                'class' => 'tinymce form-control',
                'required' => 0,
                'attributes' => [
                    'data-lang-icon',
                    'data-lang-helper' => 'contentMobile',
                    'id' => 'contentMobileElement',
                    'rows' => 20,
                ],
            ]
        ],
        'contentMobileIsEditor' => [
            'type' => 'checkbox',
            'options' => [
                'class' => 'toggleTinymce',
                'value' => 1,
                'attributes' => [
                    'data-content-id' => 'contentMobileElement',
                ],
            ],
        ],
        'layout' => [
            'type' => 'text',
            'options' => [
                'label' => 'Layout',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => [
                    'placeholder' => 'default',
                ],
            ],
        ],
        'viewScript' => [
            'type' => 'text',
            'options' => [
                'label' => 'Views script',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => [
                    'placeholder' => 'detail',
                ],
            ],
        ],
        'css' => [
            'type' => 'text',
            'options' => [
                'label' => 'CSS class',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'required' => 0,
            ],
        ],
        'startDate' => [
            'type' => 'datetime',
            'options' => [
                'label' => 'Начална дата',
                'labelClass' => 'control-label',
                'class' => 'datepicker form-control',
                'required' => 0,
                'format' => 'd.m.Y',
            ]
        ],
        'endDate' => [
            'type' => 'datetime',
            'options' => [
                'label' => 'Крайна дата',
                'labelClass' => 'control-label',
                'class' => 'datepicker form-control',
                'required' => 0,
                'format' => 'd.m.Y',
                'validators' => [
                    'dateValidator' => [
                        'validator' => 'DateIsNotEarlier',
                        'options' => [
                            'field' => 'startDate'
                        ],
                    ],
                ],
            ]
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