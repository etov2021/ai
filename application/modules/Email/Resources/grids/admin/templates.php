<?php

$router = app('router');

return array(
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'buttons' => [
        'btnAdd' => [
            'value' => 'Добавяне',
            'class' => 'btn btn-primary',
        ],
        'btnActivate' => [
            'value' => 'Активиране',
            'class' => 'btn btn-success',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => $router->assemble(\null, ['action' => 'activate']),
                'data-confirm' => 'Сигурни ли сте, че искате да активирате избраните записи?',
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
        ],
        'btnDelete' => [
            'value' => 'Изтриване',
            'class' => 'btn btn-danger',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => $router->assemble(\null, ['action' => 'delete']),
                'data-confirm' => 'Сигурни ли сте, че искате да изтриете избраните записи?',
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
                'headStyle' => 'width: 5%',
                'params' => [
                    'action' => 'edit',
                    'id' => ':id',
                ],
            ],
        ],
        'translations' => [
            'options' => [
                'sourceField' => 'id',
                'class' => 'text-center',
                'title' => ' ',
                'viewScript' => 'admin::_grid-languages',
                'headStyle' => 'width: 5%',
            ],
        ],
        'subject' => [
            'options' => [
                'sourceField' => 'subject',
                'sortable' => 1,
                'title' => 'Относно',
            ],
        ],
        'alias' => [
            'options' => [
                'title' => 'Псевдоним',
                'sourceField' => 'alias',
                'headStyle' => 'width: 20%',
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
                'hrefClass' => 'remove glyphicon glyphicon-trash',
                'params' => [
                    'action' => 'delete',
                    'id' => ':id',
                ],
            ],
        ],
    ],
);