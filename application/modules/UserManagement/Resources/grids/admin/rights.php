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
            'options' => [
                'title' => '#',
                'sourceField' => 'id',
                'headStyle' => 'width: 5%',
            ],
        ],
        'right' => [
            'type' => 'href',
            'options' => [
                'sourceField' => 'right',
                'sortable' => 0,
                'title' => 'Право',
                'headStyle' => 'width: 20%',
                'params' => [
                    'action' => 'edit',
                    'id' => ':id',
                ],
            ],
        ],
        'name' => [
            'options' => [
                'sourceField' => 'name',
                'sortable' => 0,
                'title' => 'Наименование',
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