<?php

declare(strict_types=1);

namespace Ai\McpServer;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Токените за достъп на външни клиенти и дневникът на техните извиквания. */
final class McpTokenRepository
{
    public const TOOLS = ['docs_search', 'docs_list', 'docs_read', 'ask_agent'];
    private const PREFIX = 'aimcp_';

    public function __construct(private DatabaseInterface $db, private \Closure $clock)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT `id`, `name`, `tokenPrefix`, `tools`, `documentIds`, `agentCode`, `maskPii`, `rateLimitPerMinute`, `active`, `expires`, `lastUsed`, `created` FROM `AiMcpTokens` ORDER BY `id` DESC');
    }

    /**
     * @param list<string> $tools
     * @param ?list<int> $documentIds null = всички документи (трябва да е избрано изрично)
     * @return string самият токен; показва се само сега, после се пази само хешът му
     */
    public function create(string $name, array $tools, ?array $documentIds, ?string $agentCode, bool $maskPii, int $rateLimitPerMinute, ?string $expires): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително (до 100 знака).');
        }

        $tools = array_values(array_intersect(self::TOOLS, $tools));
        if ($tools === []) {
            throw new AiException('Изберете поне един инструмент.');
        }
        if (in_array('ask_agent', $tools, true) && ($agentCode === null || $agentCode === '')) {
            throw new AiException('Инструментът ask_agent изисква избран агент.');
        }
        if ($documentIds !== null && $documentIds === [] && array_intersect($tools, ['docs_search', 'docs_list', 'docs_read']) !== []) {
            throw new AiException('Изберете документи или отбележете „всички документи“.');
        }
        if ($rateLimitPerMinute < 1 || $rateLimitPerMinute > 1000) {
            throw new AiException('Лимитът трябва да е между 1 и 1000 заявки в минута.');
        }
        if ($expires !== null && strtotime($expires) === false) {
            throw new AiException('Невалидна дата на изтичане.');
        }

        // 192 бита случайност: не е нужно соленo хеширане, а търсенето по хеш е точно.
        $token = self::PREFIX . bin2hex(random_bytes(24));

        $data = [
            'name' => $name,
            'tokenHash' => hash('sha256', $token),
            'tokenPrefix' => substr($token, 0, 12),
            'tools' => implode(',', $tools),
            'documentIds' => $documentIds === null ? null : implode(',', $documentIds),
            'agentCode' => in_array('ask_agent', $tools, true) ? $agentCode : null,
            'maskPii' => $maskPii ? 1 : 0,
            'rateLimitPerMinute' => $rateLimitPerMinute,
            'active' => 1,
            'expires' => $expires === null ? null : date('Y-m-d H:i:s', (int) strtotime($expires)),
            'created' => $this->now(),
        ];
        $this->db->execute(Sql::insert('AiMcpTokens', array_keys($data)), array_values($data));

        return $token;
    }

    public function authenticate(string $token): ?McpToken
    {
        if (!str_starts_with($token, self::PREFIX)) {
            return null;
        }

        $row = $this->db->fetchRow('SELECT * FROM `AiMcpTokens` WHERE `tokenHash` = ? AND `active` = 1', [hash('sha256', $token)]);
        if ($row === null || ($row['expires'] !== null && strtotime((string) $row['expires']) < ($this->clock)())) {
            return null;
        }

        $ids = $row['documentIds'] === null ? null : array_values(array_map('intval', array_filter(explode(',', (string) $row['documentIds']), 'strlen')));

        return new McpToken(
            (int) $row['id'],
            (string) $row['name'],
            array_values(array_intersect(self::TOOLS, explode(',', (string) $row['tools']))),
            $ids,
            $row['agentCode'] === null ? null : (string) $row['agentCode'],
            (bool) $row['maskPii'],
            (int) $row['rateLimitPerMinute'],
        );
    }

    public function touch(int $id): void
    {
        $this->db->execute('UPDATE `AiMcpTokens` SET `lastUsed` = ? WHERE `id` = ?', [$this->now(), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE `AiMcpTokens` SET `active` = ? WHERE `id` = ?', [$active ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiMcpTokens` WHERE `id` = ?', [$id]);
    }

    public function audit(int $tokenId, string $tool, string $status, int $durationMs, int $responseChars): void
    {
        $this->db->execute(
            'INSERT INTO `AiMcpCalls` (`tokenId`, `tool`, `status`, `durationMs`, `responseChars`, `created`) VALUES (?, ?, ?, ?, ?, ?)',
            [$tokenId, substr($tool, 0, 40), substr($status, 0, 20), max(0, $durationMs), max(0, $responseChars), $this->now()],
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentCalls(int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT c.`tool`, c.`status`, c.`durationMs`, c.`responseChars`, c.`created`, t.`name` AS tokenName'
            . ' FROM `AiMcpCalls` c INNER JOIN `AiMcpTokens` t ON t.`id` = c.`tokenId` ORDER BY c.`id` DESC LIMIT ' . max(1, min($limit, 200)),
        );
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s', ($this->clock)());
    }
}
