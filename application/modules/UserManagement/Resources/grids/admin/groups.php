<?php

use UserManagement\Model\Groups;

return array(
    'paginatorPlacement' => 'bottom',
    'paginatorAlways' => 0,
    'buttons' => array(
        'btnAdd' => array(
            'value' => 'Добавяне',
            'class' => 'btn btn-primary'
        ),
    ),
    'columns' => array(
        'id' => array(
            'options' => array(
                'title' => '#',
                'sourceField' => 'id',
                'headStyle' => 'width: 5%',
            )
        ),
        'name' => array(
            'type' => 'href',
            'options' => array(
                'sourceField' => 'name',
                'sortable' => 1,
                'title' => 'Група',
                'params' => array(
                    'action' => 'edit',
                    'id' => ':id'
                )
            )
        ),
        'parent' => array(
            'type' => 'pairs',
            'options' => array(
                'title' => 'Родител',
                'sourceField' => 'parentId',
                'headStyle' => 'width: 20%',
                'callable' => [new Groups(), 'fetchCachedPairs'],
                'notFoundText' => 'Без родител',
            )
        ),
        'alias' => array(
            'options' => array(
                'title' => 'Псевдоним',
                'sourceField' => 'alias',
                'headStyle' => 'width: 20%'
            )
        ),
    )
);