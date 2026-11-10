<?php

$router = app('router');

return array(
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'buttons' => [
        'btnAdd' => [
            'value' => 'Добавяне',
            'class' => 'btn btn-primary',
            'resources' => [
                'Navigation\Controller\Admin\Index@add',
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
                'Navigation\Controller\Admin\Index@activate',
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
                'Navigation\Controller\Admin\Index@deactivate',
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
                'Navigation\Controller\Admin\Index@delete',
            ],
        ]
    ],
    'columns' => array(
        'ids' => [
            'type' => 'checkbox',
            'options' => [
                'sourceField' => 'id',
                'checkAll' => 1,
                'class' => 'text-center',
                'headClass' => 'text-center',
                'headStyle' => 'width: 5%',
            ]
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
        'items' => array(
            'type' => 'href',
            'options' => array(
                'title' => 'Елементи',
                'text' => ' ',
                'sourceField' => 'id',
                'headStyle' => 'width: 5%',
                'class' => 'text-center',
                'headClass' => 'text-center',
                'hrefClass' => 'mdi mdi-format-list-bulleted',
                'params' => array(
                    'action' => 'items',
                    'menuId' => ':id'
                )
            )
        ),
        'removeCache' => array(
            'options' => array(
                'sourceField' => 'id',
                'sortable' => 0,
                'title' => 'Кеш елементи',
                'class' => 'text-center',
                'headStyle' => 'width: 5%',
                'viewScript' => 'admin/index/_grid-remove-cache'
            )
        ),
        'name' => array(
            'options' => array(
                'sourceField' => 'name',
                'title'  => 'Наименование',
                'reset'  => 0,
                'sortable' => 1,
                'viewScript' => 'admin/index/_grid-name'
            )
        ),
        'alias' => array(
            'options' => array(
                'sourceField' => 'alias',
                'sortable' => 1,
                'title' => 'Псевдоним',
                'headStyle' => 'width: 15%'
            )
        ),
        'active' => array(
            'type' => 'boolean',
            'options' => array(
                'sourceField' => 'active',
                'sortable' => 1,
                'title' => 'Активност',
                'class' => 'text-center',
                'true' => '<span class="fa fa-check"></span>',
                'false' => '<span class="fa fa-ban"></span>',
                'headStyle' => 'width: 5%'
            )
        ),
        'delete' => array(
            'type' => 'href',
            'options' => array(
                'text'   => ' ',
                'headStyle'  => 'width: 5%',
                'class'    => 'text-center',
                'hrefClass' => 'remove mdi mdi-delete-forever',
                'resources' => [
                    'Navigation\Controller\Admin\Index@delete',
                ],
                'params' => array(
                    'action' => 'delete',
                    'id' => ':id'
                )
            )
        ),
    )
);