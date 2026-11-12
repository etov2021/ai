<?php

return [
    'elements' => [
        'input' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Входен текст',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 5, 'maxlength' => 20000],
            ],
        ],
        'csrf' => ['type' => 'csrf'],
        'btnRun' => ['type' => 'submit', 'options' => ['value' => 'Изпълни', 'class' => 'btn btn-primary']],
    ],
];
