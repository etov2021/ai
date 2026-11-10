<?php

use Domains\Model\Domains;
use Pages\Model\Entity\Page;

$treeHelper = new Categories\Helper\Tree('pages');

return [
    'elements' => [
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Заглавие на страницата / SEO friendly URL',
                'labelClass' => 'control-label',
                'class' => 'form-control',
                'belongsTo' => 'filters',
            ]
        ],
        'type' => [
            'type' => 'select',
            'options' => [
                'label' => 'Page type',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'isArray' => 1,
                'attributes' => [
                    'data-placeholder' => 'Изберете тип',
                    'style' => 'display: none',
                ],
                'multiOptions' => Page::$TYPES,
                'translateValue' => true,
                'belongsTo' => 'filters',
            ]
        ],
        'categoryId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Категория',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'isArray' => 1,
                'attributes' => [
                    'data-placeholder' => 'Изберете категория',
                    'style' => 'display: none',
                ],
                'multiOptions' => $treeHelper->toSelectOptions($treeHelper->getTree(), ' - '),
                'belongsTo' => 'filters',
            ]
        ],
        'domainId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Domain',
                'labelClass' => 'control-label',
                'class' => 'form-control select2',
                'isArray' => 1,
                'attributes' => [
                    'id' => 'domainId',
                    'data-placeholder' => translate('Select domain'),
                ],
                'multiOptions' => (new Domains())->fetchCachedPairs(),
                'belongsTo' => 'filters',
            ]
        ],
        'filter'  => ['type' => 'submit', 'options' => ['value' => 'Филтриране', 'class' => 'btn btn-primary', 'belongsTo' => 'filters']],
        'reset' => ['type' => 'submit', 'options' => ['value' => 'Нулиране', 'class' => 'btn btn-default', 'belongsTo' => 'filters']],
    ]
];