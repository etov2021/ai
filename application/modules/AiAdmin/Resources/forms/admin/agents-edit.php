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
        'modelId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Модел',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'escalationModelId' => [
            'type' => 'select',
            'options' => [
                'label' => 'Модел за ескалация (по-мощен, по избор)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'emptyOption' => '- без ескалация -',
            ],
        ],
        'systemPrompt' => [
            'type' => 'textarea',
            'options' => [
                'label' => 'Инструкции (system prompt)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['rows' => 12, 'maxlength' => 20000],
            ],
        ],
        'temperature' => [
            'type' => 'text',
            'options' => [
                'label' => 'Температура (0-2, празно = по подразбиране на модела)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
            ],
        ],
        'maxTokens' => [
            'type' => 'number',
            'options' => [
                'label' => 'Макс. изходни токени',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['min' => 1],
            ],
        ],
        'maxSteps' => [
            'type' => 'number',
            'options' => [
                'label' => 'Макс. стъпки (1-20)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['min' => 1, 'max' => 20],
            ],
        ],
        'timeoutSeconds' => [
            'type' => 'number',
            'options' => [
                'label' => 'Макс. време (секунди, 5-900)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['min' => 5, 'max' => 900],
            ],
        ],
        'active' => ['type' => 'checkbox', 'options' => ['label' => 'Активен']],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack' => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    ],
];
