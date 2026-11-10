<?php

use UserManagement\Model\Groups;

return array(
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'buttons' => array(
        'btnAdd' => [
            'value' => 'Добавяне',
            'class' => 'btn btn-primary'
        ],
        'btnActivate' => [
            'value' => 'Активиране',
            'class' => 'btn btn-success',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => app('router')->assemble(\null, ['action' => 'activate']),
                'data-confirm' => 'Сигурни ли сте, че искате да активирате избраните записи?'
            ]
        ],
        'btnDeactivate' => [
            'value' => 'Деактивиране',
            'class' => 'btn btn-warning',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => app('router')->assemble(\null, ['action' => 'deactivate']),
                'data-confirm' => 'Сигурни ли сте, че искате да деактивирате избраните записи?'
            ]
        ],
        'btnDelete' => [
            'value' => 'Изтриване',
            'class' => 'btn btn-danger',
            'attributes' => [
                'data-rel' => 'ids[]',
                'data-action' => app('router')->assemble(\null, ['action' => 'delete']),
                'data-confirm' => 'Сигурни ли сте, че искате да изтриете избраните записи?'
            ]
        ]
    ),
    'columns' => array(
        'ids' => array(
            'type' => 'checkbox',
            'options' => array(
                'headClass' => 'text-center',
                'class' => 'text-center',
                'checkAll' => 1,
                'sourceField' => 'id',
                'headStyle' => 'width: 5%'
            )
        ),
        'id' => array(
            'type' => 'href',
            'options' => array(
                'title' => '#',
                'sourceField' => 'id',
                'sortable' => 1,
                'headStyle' => 'width: 5%',
                'params' => array(
                    'action' => 'edit',
                    'id' => ':id'
                )
            )
        ),
        'username' => array(
            'type' => 'href',
            'options' => array(
                'sourceField' => 'username',
                'sortable' => 1,
                'title' => 'Потребител',
                'params' => array(
                    'action' => 'edit',
                    'id' => ':id'
                )
            )
        ),
        'firstName' => array(
            'options' => array(
                'title' => 'Имена: собствено, бащино, фамилно',
                'sourceField' => 'firstName',
                'headStyle' => 'width: 20%',
                'filter' => function ($v, $item) {
                    return $item['firstName'] . ' ' . $item['middleName'] . ' ' . $item['lastName'];
                },
            ),
        ),
        'groupId' => array(
            'type' => 'pairs',
            'options' => array(
                'title' => 'Роля',
                'sourceField' => 'groupId',
                'pairs' => (new Groups())->fetchCachedPairs(),
                'headStyle' => 'width: 20%',
            )
        ),
        'active' => array(
            'type' => 'boolean',
            'options' => array(
                'sourceField' => 'active',
                'sortable' => 1,
                'title' => 'Активност',
                'headClass' => 'text-center',
                'class' => 'text-center',
                'true' => '<span class="fa fa-check"></span>',
                'false' => '<span class="fa fa-ban"></span>',
                'headStyle' => 'width: 5%'
            )
        ),
        'killSession' => [
            'options' => [
                'sourceField' => 'id',
                'headStyle'  => 'width: 5%',
                'class'  => 'text-center',
                'viewScript' => 'admin/users/_sessions',
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
    )
);