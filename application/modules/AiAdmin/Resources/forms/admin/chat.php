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
        'message' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Съобщение',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 4, 'maxlength' => 20000],
            ],
        ],
        'csrf' => ['type' => 'csrf'],
        'btnSend' => ['type' => 'submit', 'options' => ['value' => 'Изпрати', 'class' => 'btn btn-primary']],
    ],
];
