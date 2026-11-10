<?php

return array(
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'columns' => [
        'id' => [
            'options' => [
                'sourceField' => 'id',
                'sortable' => 1,
                'title' => '#',
                'style' => 'width: 40px; white-space: nowrap;',
            ],
        ],
        'time' => [
            'options' => [
                'sourceField' => 'time',
                'sortable' => 0,
                'title' => 'Дата',
                'style' => 'width: 10%; white-space: nowrap;',
                'viewScript' => 'admin/index/_grid-time'
            ],
        ],
        'creatorName' => [
            'options' => [
                'sourceField' => 'creatorName',
                'sortable' => 0,
                'title' => 'Потребител',
            ],
        ],
        'module' => [
            'options' => [
                'sourceField' => 'module',
                'sortable' => 0,
                'title' => 'Модул',
                'style' => 'width: 10%;',
                'filter' => function ($v) {
                    return translate('module ' . $v);
                },
            ],
        ],
        'controller' => [
            'options' => [
                'sourceField' => 'controller',
                'sortable' => 0,
                'title' => 'Контролер',
                'style' => 'width: 10%;',
                'filter' => function ($v) {
                    return translate('controller ' . $v);
                },
            ],
        ],
        'action' => [
            'options' => [
                'sourceField' => 'action',
                'sortable' => 0,
                'title' => 'Действие',
                'style' => 'width: 10%;',
                'filter' => function ($v) {
                    return translate('action ' . $v);
                },
            ],
        ],
        'message' => [
            'options' => [
                'sourceField' => 'message',
                'sortable' => 0,
                'title' => 'Съобщение',
                'viewScript' => 'admin/index/_grid-message',
                'style' => 'width: 10%;',
            ],
        ],
    ],
);