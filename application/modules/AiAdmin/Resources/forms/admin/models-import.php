<?php

return [
    'elements' => [
        'provider' => [
            'type' => 'select',
            'options' => [
                'label' => 'Ollama доставчик',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'csrf' => ['type' => 'csrf'],
        'btnImport' => ['type' => 'submit', 'options' => ['value' => 'Импортирай модели', 'class' => 'btn btn-primary']],
    ],
];
