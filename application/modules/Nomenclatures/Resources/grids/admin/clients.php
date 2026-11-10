<?php

$router = app('router');

return [
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'buttons' => [
        'btnAdd' => [
            'value' => 'Добавяне',
            'class' => 'btn btn-primary',
            'resources' => [
                'Nomenclatures\Controller\Admin\Clients@add',
            ],
        ],
        'btnActivate' => [
            'value' => 'Активиране',
            'class' => 'btn btn-success',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => $router->assemble(\null, ['action' => 'activate']),
                'data-confirm' => 'Сигурни ли сте, че искате да активирате избраните записи?',
            ],
            'resources' => [
                'Nomenclatures\Controller\Admin\Clients@activate',
            ],
        ],
        'btnDeactivate' => [
            'value' => 'Деактивиране',
            'class' => 'btn btn-warning',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => $router->assemble(\null, ['action' => 'deactivate']),
                'data-confirm' => 'Сигурни ли сте, че искате да деактивирате избраните записи?',
            ],
            'resources' => [
                'Nomenclatures\Controller\Admin\Clients@deactivate',
            ],
        ],
        'btnDelete' => [
            'value' => 'Изтриване',
            'class' => 'btn btn-danger',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => $router->assemble(\null, ['action' => 'delete']),
                'data-confirm' => 'Сигурни ли сте, че искате да изтриете избраните записи?',
            ],
            'resources' => [
                'Nomenclatures\Controller\Admin\Clients@delete',
            ],
        ],
    ],
    'columns' => [
        'ids' => [
            'type' => 'checkbox',
            'options' => [
                'headClass' => 'text-center',
                'class' => 'text-center',
                'checkAll' => 1,
                'sourceField' => 'id',
                'headStyle' => 'width: 5%',
            ],
        ],
        'id' => [
            'type' => 'href',
            'options' => [
                'title' => '#',
                'sourceField' => 'id',
                'sortable' => 1,
                'headStyle' => 'width: 5%',
                'params' => [
                    'action' => 'edit',
                    'id' => ':id',
                ],
            ],
        ],
        'name' => [
            'type' => 'href',
            'options' => [
                'title' => 'Наименование',
                'sourceField' => 'name',
                'sortable' => 1,
                'params' => [
                    'action' => 'edit',
                    'id' => ':id',
                ],
            ],
        ],
        'abbr' => [
            'options' => [
                'title' => 'Абревиатура',
                'sourceField' => 'abbr',
                'sortable' => 1,
                'headStyle' => 'width: 10%',
            ],
        ],
        'active' => [
            'type' => 'boolean',
            'options' => [
                'sourceField' => 'active',
                'sortable' => 1,
                'title' => 'Активност',
                'headClass' => 'text-center',
                'class' => 'text-center',
                'true' => '<span class="fa fa-check"></span>',
                'false' => '<span class="fa fa-ban"></span>',
                'headStyle' => 'width: 5%',
            ],
        ],
        'delete' => [
            'type' => 'href',
            'options' => [
                'text'   => ' ',
                'headStyle'  => 'width: 5%',
                'class'  => 'text-center',
                'hrefClass' => 'remove fa fa-remove red',
                'params' => [
                    'action' => 'delete',
                    'id' => ':id',
                ],
            ],
        ],
    ],
];