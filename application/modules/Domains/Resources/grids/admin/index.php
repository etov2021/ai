<?php

$router = app('router');
$languages = [];
foreach (app('languages') as $language) {
    $languages[$language['id']] = $language['name'];
}
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
        'protocol' => [
            'options' => [
                'title' => 'Протокол',
                'sourceField' => 'protocol',
                'headStyle' => 'width: 5%',
            ],
        ],
        'name' => [
            'type' => 'href',
            'options' => [
                'sourceField' => 'name',
                'sortable' => 1,
                'title' => 'Domain',
                'params' => [
                    'action' => 'edit',
                    'id' => ':id',
                ],
            ],
        ],
        'addresses' => [
            'options' => [
                'sourceField' => 'addresses',
                'sortable' => 0,
                'title' => 'Адреси',
                'headStyle' => 'width: 15%',
                'filter' => function ($v) {
                    return nl2br($v);
                },
            ],
        ],
        'languages' => [
            'type' => 'pairs',
            'options' => [
                'title' => 'Езици',
                'sourceField' => 'languages',
                'headStyle' => 'width: 10%',
                'pairs' => $languages,
            ],
        ],
        'defaultLanguage' => [
            'type' => 'pairs',
            'options' => [
                'title' => 'Базов език',
                'sourceField' => 'defaultLanguage',
                'headStyle' => 'width: 10%',
                'pairs' => $languages,
            ],
        ],
        'countryCode' => [
            'options' => [
                'sourceField' => 'countryCode',
                'sortable' => 1,
                'title' => 'Country code',
                'headStyle' => 'width: 10%',
            ],
        ],
        'template' => [
            'options' => [
                'title' => 'Шаблон',
                'sourceField' => 'template',
                'headStyle' => 'width: 10%',
            ],
        ],
    ],
);