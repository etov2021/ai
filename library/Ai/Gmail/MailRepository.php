<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Метаданни и анализ на получените писма. Пълният текст не се записва. */
final class MailRepository
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function has(string $gmailId): bool
    {
        return $this->db->fetchRow('SELECT `id` FROM `AiMailMessages` WHERE `gmailId` = ?', [$gmailId]) !== null;
    }

    public function add(string $gmailId, string $from, string $subject, string $snippet, string $receivedAt): void
    {
        $data = [
            'gmailId' => $gmailId,
            'fromAddr' => mb_substr($from, 0, 255),
            'subject' => mb_substr($subject, 0, 255),
            'snippet' => mb_substr($snippet, 0, 255),
            'receivedAt' => $receivedAt,
            'created' => date('Y-m-d H:i:s'),
        ];
        $this->db->execute(Sql::insert('AiMailMessages', array_keys($data)), array_values($data));
    }

    /** @return list<array<string, mixed>> писма без анализ, които още не са изчерпали опитите */
    public function pending(int $maxAttempts, int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT `id`, `gmailId`, `fromAddr`, `subject` FROM `AiMailMessages` WHERE `analysis` IS NULL AND `attempts` < ? ORDER BY `receivedAt` DESC LIMIT ' . max(1, $limit),
            [$maxAttempts],
        );
    }

    public function setAnalysis(int $id, string $analysis): void
    {
        $this->db->execute('UPDATE `AiMailMessages` SET `analysis` = ?, `attempts` = `attempts` + 1 WHERE `id` = ?', [mb_substr($analysis, 0, 4000), $id]);
    }

    public function failed(int $id): void
    {
        $this->db->execute('UPDATE `AiMailMessages` SET `attempts` = `attempts` + 1 WHERE `id` = ?', [$id]);
    }

    public function unseenCount(): int
    {
        return (int) ($this->db->fetchRow('SELECT COUNT(*) AS c FROM `AiMailMessages` WHERE `seen` = 0')['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit, bool $onlyUnseen = false): array
    {
        return $this->db->fetchAll(
            'SELECT `id`, `fromAddr`, `subject`, `snippet`, `receivedAt`, `analysis`, `seen` FROM `AiMailMessages`'
            . ($onlyUnseen ? ' WHERE `seen` = 0' : '') . ' ORDER BY `receivedAt` DESC, `id` DESC LIMIT ' . max(1, $limit),
        );
    }

    public function markAllSeen(): void
    {
        $this->db->execute('UPDATE `AiMailMessages` SET `seen` = 1 WHERE `seen` = 0');
    }
}
