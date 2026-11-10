<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Message\Message;
use Ai\Message\Role;
use Ai\Message\ToolCall;

final class ConversationRepository
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function create(int $agentId, ?int $userId, string $title): Conversation
    {
        $now = date('Y-m-d H:i:s');
        $title = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $title)), 0, 200);
        $this->db->execute(
            Sql::insert('AiConversations', ['agentId', 'userId', 'title', 'created', 'updated']),
            [$agentId, $userId, $title, $now, $now],
        );

        return new Conversation($this->db->lastInsertId(), $agentId, $userId, $title);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiConversations` WHERE `id` = ?', [$id]);
    }

    public function find(int $id): ?Conversation
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiConversations` WHERE `id` = ?', [$id]);

        return $row === null ? null : new Conversation((int) $row['id'], (int) $row['agentId'], $row['userId'] !== null ? (int) $row['userId'] : null, (string) $row['title']);
    }

    /**
     * Последните $limit съобщения. Историята започва със съобщение на потребителя,
     * защото tool резултат без предхождащото го извикване се отхвърля от доставчиците.
     *
     * @return list<Message>
     */
    public function history(int $conversationId, int $limit): array
    {
        $rows = array_reverse($this->db->fetchAll(
            'SELECT * FROM `AiMessages` WHERE `conversationId` = ? ORDER BY `seq` DESC LIMIT ' . max(1, min(500, $limit)),
            [$conversationId],
        ));

        while ($rows !== [] && $rows[0]['role'] !== Role::User->value) {
            array_shift($rows);
        }

        return array_map(static function (array $row): Message {
            $calls = array_map(
                static fn (array $c): ToolCall => new ToolCall((string) $c['id'], (string) $c['name'], (array) ($c['arguments'] ?? []), null, $c['opaque'] ?? null),
                $row['toolCalls'] ? (json_decode((string) $row['toolCalls'], true) ?: []) : [],
            );

            return new Message(Role::from((string) $row['role']), $row['content'] !== null ? (string) $row['content'] : null, $calls, $row['toolCallId'] ?? null, $row['name'] ?? null);
        }, $rows);
    }

    /** @param list<Message> $messages */
    public function append(int $conversationId, array $messages): void
    {
        $seq = (int) ($this->db->fetchRow('SELECT MAX(`seq`) AS maxSeq FROM `AiMessages` WHERE `conversationId` = ?', [$conversationId])['maxSeq'] ?? 0);
        $now = date('Y-m-d H:i:s');

        foreach ($messages as $m) {
            $calls = $m->toolCalls === [] ? null : json_encode(array_map(static fn (ToolCall $c): array => [
                'id' => $c->id, 'name' => $c->name, 'arguments' => $c->arguments, 'opaque' => $c->opaque,
            ], $m->toolCalls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            $this->db->execute(
                Sql::insert('AiMessages', ['conversationId', 'seq', 'role', 'content', 'toolCalls', 'toolCallId', 'name', 'created']),
                [$conversationId, ++$seq, $m->role->value, $m->content, $calls, $m->toolCallId, $m->name, $now],
            );
        }

        $this->db->execute('UPDATE `AiConversations` SET `updated` = ? WHERE `id` = ?', [$now, $conversationId]);
    }
}
