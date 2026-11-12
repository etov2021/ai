<?php

return [
    'elements' => [
        'agent' => [
            'type' => 'select',
            'options' => [
                'label' => 'Агент',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'mode' => [
            'type' => 'select',
            'options' => [
                'label' => 'Действие',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'fields' => [
            'type' => 'text',
            'options' => [
                'label' => 'Полета за извличане (само при „Извличане на данни“), напр. номер, дата, контрагент, обща сума',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['maxlength' => 500],
            ],
        ],
        'csrf' => ['type' => 'csrf'],
        'btnRun' => ['type' => 'submit', 'options' => ['value' => 'Обработи', 'class' => 'btn btn-primary']],
    ],
];
