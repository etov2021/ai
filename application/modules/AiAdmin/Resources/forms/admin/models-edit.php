<?php

return [
    'elements' => [
        'model' => [
            'type' => 'text',
            'options' => [
                'label' => 'Идентификатор на модела (както го очаква API-то)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 120, 'autocomplete' => 'off'],
            ],
        ],
        'label' => [
            'type' => 'text',
            'options' => [
                'label' => 'Етикет',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 100],
            ],
        ],
        'inputPricePerMillion' => [
            'type' => 'text',
            'options' => [
                'label' => 'Цена входни токени (EUR / 1 млн.)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'outputPricePerMillion' => [
            'type' => 'text',
            'options' => [
                'label' => 'Цена изходни токени (EUR / 1 млн.)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'contextWindow' => [
            'type' => 'number',
            'options' => [
                'label' => 'Контекстен прозорец (токени)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['min' => 1],
            ],
        ],
        'maxOutputTokens' => [
            'type' => 'number',
            'options' => [
                'label' => 'Макс. изходни токени',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['min' => 1],
            ],
        ],
        'supportsTools' => ['type' => 'checkbox', 'options' => ['label' => 'Поддържа инструменти']],
        'active' => ['type' => 'checkbox', 'options' => ['label' => 'Активен']],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack' => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ],
];
