<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Security\KeyVault;
use AiAdmin\Service\BaseController;

class Index extends BaseController
{
    private const SECTIONS = [
        ['providers', 'Доставчици', 'API ключове и адреси на LLM доставчиците'],
        ['models', 'Модели', 'Модели и цени за 1 млн. токена'],
        ['agents', 'Агенти', 'Инструкции, модел, инструменти и ограничения'],
        ['tools', 'Инструменти', 'Контролирани външни API-та за агентите'],
        ['workflows', 'Работни потоци', 'Вериги от агенти: стъпки, маршрутизация, цикъл оценка-подобрение'],
        ['gmail', 'Gmail', 'Свързване на пощенски акаунт (само четене) за агентите'],
        ['mail', 'Получена поща', 'Нови писма и анализ от агент (известия)'],
        ['budgets', 'Лимити и бюджети', 'Заявки в минута и разходи по обхват'],
        ['usage', 'Използване и логове', 'Токени, разходи, грешки и време'],
        ['chat', 'Тестов чат', 'Разговор с агент от админа'],
    ];

    public function indexAction()
    {
        $keyReady = true;
        try {
            KeyVault::fromEnv();
        } catch (AiException $e) {
            $keyReady = false;
        }

        $today = date('Y-m-d');
        $usage = $this->ai()->usage();

        return [
            'keyReady' => $keyReady,
            'costToday' => $this->safely(fn () => $usage->spent('global', '', $today, $today), 0.0),
            'costMonth' => $this->safely(fn () => $usage->spent('global', '', date('Y-m-01'), $today), 0.0),
            'sections' => array_map(fn (array $s): array => ['title' => $s[1], 'text' => $s[2], 'url' => $this->url($s[0])], self::SECTIONS),
        ];
    }
}
