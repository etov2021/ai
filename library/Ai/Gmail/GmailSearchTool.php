<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Contract\ToolInterface;
use Ai\Message\ToolDefinition;

/** Търсене в пощата със синтаксиса на Gmail (from:, subject:, after:, is:unread...). */
final class GmailSearchTool implements ToolInterface
{
    public const NAME = 'mail_search';

    public function __construct(private GmailClient $client)
    {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            self::NAME,
            'Търси писма в пощата на Gmail. Ползва синтаксиса на Gmail, напр. from:иван subject:оферта after:2026/10/01 is:unread. Връща списък с id, подател, тема, дата и откъс. За цялото писмо ползвай mail_read с id.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300, 'description' => 'Заявка в синтаксиса на Gmail'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'Максимален брой писма (по подразбиране 5)'],
                ],
                'required' => ['query'],
            ],
        );
    }

    public function execute(array $arguments): string
    {
        $messages = $this->client->search((string) $arguments['query'], (int) ($arguments['limit'] ?? 5));
        if ($messages === []) {
            return 'Няма намерени писма.';
        }

        $lines = [GmailReadTool::NOTICE];
        foreach ($messages as $i => $m) {
            $lines[] = sprintf("[%d] id=%s | От: %s | Тема: %s | Дата: %s\n    %s", $i + 1, $m['id'], $m['from'], $m['subject'], $m['date'], $m['snippet']);
        }

        return implode("\n", $lines);
    }
}
