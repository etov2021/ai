<?php

namespace AiAdmin\Service;

/** Данни за камбанката в хедъра. Никога не хвърля: липсваща таблица или ключ не трябва да чупят админа. */
final class MailNotifications
{
    /** @return array{count: int, items: list<array<string, mixed>>, url: string} */
    public static function summary(int $limit = 5): array
    {
        $empty = ['count' => 0, 'items' => [], 'url' => ''];

        try {
            if (!is_allowed('AiAdmin\Controller\Admin\Mail@index')) {
                return $empty;
            }

            $repo = AiServices::get()->mailMessages();

            return [
                'count' => $repo->unseenCount(),
                'items' => $repo->latest($limit, true),
                'url' => route_reset('admin', ['module' => 'ai-admin', 'controller' => 'mail', 'action' => 'index', 'id' => null]),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }
}
