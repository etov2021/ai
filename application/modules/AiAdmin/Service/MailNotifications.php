<?php

namespace AiAdmin\Service;

/** Данни за камбанката в хедъра: нови писма и чакащи предложения за събития. Никога не хвърля: липсваща таблица или ключ не трябва да чупят админа. */
final class MailNotifications
{
    /** @return array{count: int, items: list<array<string, mixed>>, events: list<array<string, mixed>>, url: string, calendarUrl: string} */
    public static function summary(int $limit = 5): array
    {
        $result = ['count' => 0, 'items' => [], 'events' => [], 'url' => '', 'calendarUrl' => ''];

        // Двата източника са независими: липсваща таблица на единия не скрива другия.
        try {
            if (is_allowed('AiAdmin\Controller\Admin\Mail@index')) {
                $repo = AiServices::get()->mailMessages();
                $result['count'] += $repo->unseenCount();
                $result['items'] = $repo->latest($limit, true);
                $result['url'] = route_reset('admin', ['module' => 'ai-admin', 'controller' => 'mail', 'action' => 'index', 'id' => null]);
            }
        } catch (\Throwable) {
            $result['url'] = '';
        }

        try {
            if (is_allowed('AiAdmin\Controller\Admin\Calendar@index')) {
                $proposals = AiServices::get()->calendarProposals();
                $count = $proposals->pendingCount();
                $result['count'] += $count;
                $result['events'] = array_slice($proposals->pending(), 0, $limit);
                $result['calendarUrl'] = route_reset('admin', ['module' => 'ai-admin', 'controller' => 'calendar', 'action' => 'index', 'id' => null]);
            }
        } catch (\Throwable) {
            $result['calendarUrl'] = '';
        }

        return $result;
    }
}
