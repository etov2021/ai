<?php

return [
    'elements' => [
        'code' => [
            'type' => 'text',
            'options' => [
                'label' => 'Код (латиница, след запис не се променя)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 40, 'autocomplete' => 'off'],
            ],
        ],
        'name' => [
            'type' => 'text',
            'options' => [
                'label' => 'Име',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['maxlength' => 100],
            ],
        ],
        'type' => [
            'type' => 'select',
            'options' => [
                'label' => 'Тип',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'baseUrl' => [
            'type' => 'text',
            'options' => [
                'label' => 'Базов адрес (по избор за OpenAI/Anthropic/Gemini/Ollama - по подразбиране http://localhost:11434/v1; задължителен за съвместими)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['maxlength' => 255, 'placeholder' => 'https://...'],
            ],
        ],
        'apiKey' => [
            'type' => 'password',
            'options' => [
                'label' => 'API ключ (оставете празно, за да запазите текущия)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['autocomplete' => 'new-password'],
            ],
        ],
        'timeoutSeconds' => [
            'type' => 'number',
            'options' => [
                'label' => 'Таймаут (секунди)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['min' => 1, 'max' => 600],
            ],
        ],
        'active' => [
            'type' => 'checkbox',
            'options' => ['label' => 'Активен'],
        ],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnClearKey' => ['type' => 'submit', 'options' => ['value' => 'Изтрий ключа', 'class' => 'btn btn-danger']],
        'btnBack' => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ],
];
