<?php

return [
    'elements' => [
        'scope' => [
            'type' => 'select',
            'options' => [
                'label' => 'Обхват',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
            ],
        ],
        'scopeKey' => [
            'type' => 'text',
            'options' => [
                'label' => 'Ключ ("*" = за всеки; празно за глобално)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['maxlength' => 40, 'autocomplete' => 'off'],
            ],
        ],
        'rateLimitPerMinute' => [
            'type' => 'number',
            'options' => [
                'label' => 'Заявки / минута (празно = без лимит, 0 = забранено)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
                'attributes' => ['min' => 0],
            ],
        ],
        'dailyLimit' => [
            'type' => 'text',
            'options' => [
                'label' => 'Дневен бюджет, EUR (празно = без лимит)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
            ],
        ],
        'monthlyLimit' => [
            'type' => 'text',
            'options' => [
                'label' => 'Месечен бюджет, EUR (празно = без лимит)',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 0,
            ],
        ],
        'warnPercent' => [
            'type' => 'number',
            'options' => [
                'label' => 'Предупреждение при % от бюджета',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'required' => 1,
                'attributes' => ['min' => 1, 'max' => 100],
            ],
        ],
        'active' => ['type' => 'checkbox', 'options' => ['label' => 'Активно']],
        'csrf' => ['type' => 'csrf'],
        'btnSave' => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
    ],
];
