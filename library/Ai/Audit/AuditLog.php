<?php

declare(strict_types=1);

namespace Ai\Audit;

use Ai\Security\Redactor;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/**
 * Единен дневник: кой (потребител, MCP токен, cron), какво, върху какво, с какъв резултат.
 * Пази само безопасни данни: ключове и пароли се маскират, дългите текстове се съкращават, текстовете на заявките не се записват.
 * Грешка при запис никога не прекъсва работата на приложението.
 */
final class AuditLog
{
    private const SENSITIVE_KEY = '/key|token|secret|pass|auth|csrf|credential|cookie/i';
    private const MAX_DETAILS_CHARS = 4000;

    /** @var array{type?: string, id?: ?int, name?: ?string, ip?: ?string}|null */
    private ?array $actorOverride = null;

    /**
     * @param \Closure(): array{type?: string, id?: ?int, name?: ?string, ip?: ?string} $context кой е текущият деец (от заявката)
     * @param int $retentionDays 0 = записите не се трият
     */
    public function __construct(
        private DatabaseInterface $db,
        private \Closure $clock,
        private \Closure $context,
        private int $retentionDays = 0,
    ) {
    }

    /**
     * Задава деец за остатъка от заявката (напр. MCP токен след удостоверяване), докато няма потребител в сесията.
     *
     * @param array{type?: string, id?: ?int, name?: ?string, ip?: ?string}|null $actor
     */
    public function actingAs(?array $actor): void
    {
        $this->actorOverride = $actor;
    }

    /**
     * @param array<string, mixed> $details
     * @param array{type?: string, id?: ?int, name?: ?string, ip?: ?string}|null $actor подменя текущия деец (напр. MCP токен)
     */
    public function record(string $action, ?string $entityType = null, ?string $entityId = null, array $details = [], string $result = 'ok', ?array $actor = null): void
    {
        try {
            $actor ??= $this->actorOverride ?? ($this->context)();
            $json = $details === [] ? null : json_encode(self::sanitize($details), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            $data = [
                'created' => date('Y-m-d H:i:s', ($this->clock)()),
                'actorType' => substr((string) ($actor['type'] ?? 'system'), 0, 10),
                'actorId' => isset($actor['id']) ? (int) $actor['id'] : null,
                'actorName' => isset($actor['name']) ? mb_substr((string) $actor['name'], 0, 100) : null,
                'ip' => isset($actor['ip']) ? substr((string) $actor['ip'], 0, 45) : null,
                'action' => substr($action, 0, 60),
                'entityType' => $entityType === null ? null : substr($entityType, 0, 40),
                'entityId' => $entityId === null ? null : mb_substr($entityId, 0, 80),
                'result' => substr($result, 0, 10),
                'details' => $json === false || $json === null ? null : mb_substr($json, 0, self::MAX_DETAILS_CHARS),
            ];
            $this->db->execute(Sql::insert('AiAudit', array_keys($data)), array_values($data));

            // Оправя се от време на време, без отделна задача.
            if ($this->retentionDays > 0 && random_int(1, 200) === 1) {
                $this->purge($this->retentionDays);
            }
        } catch (\Throwable $e) {
            error_log('AiAudit: записът не успя: ' . $e->getMessage());
        }
    }

    public function purge(int $days): int
    {
        return $this->db->execute('DELETE FROM `AiAudit` WHERE `created` < ?', [date('Y-m-d H:i:s', ($this->clock)() - max(1, $days) * 86400)]);
    }

    /**
     * @param array{action?: string, result?: string, actor?: string, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->where($filters);

        return $this->db->fetchAll(
            'SELECT `id`, `created`, `actorType`, `actorId`, `actorName`, `ip`, `action`, `entityType`, `entityId`, `result`, `details` FROM `AiAudit`'
            . $where . ' ORDER BY `id` DESC LIMIT ' . max(1, min($limit, 5000)) . ' OFFSET ' . max(0, $offset),
            $params,
        );
    }

    /** @param array{action?: string, result?: string, actor?: string, from?: string, to?: string} $filters */
    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);

        return (int) ($this->db->fetchRow('SELECT COUNT(*) AS n FROM `AiAudit`' . $where, $params)['n'] ?? 0);
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function where(array $filters): array
    {
        $conditions = [];
        $params = [];

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            // Префикс (напр. "agent."): специалните знаци на LIKE се екранират.
            $conditions[] = '`action` LIKE ?';
            $params[] = addcslashes($action, '%_\\') . '%';
        }
        if (in_array($filters['result'] ?? '', ['ok', 'error', 'denied'], true)) {
            $conditions[] = '`result` = ?';
            $params[] = $filters['result'];
        }
        $actor = trim((string) ($filters['actor'] ?? ''));
        if ($actor !== '') {
            $conditions[] = '`actorName` LIKE ?';
            $params[] = '%' . addcslashes($actor, '%_\\') . '%';
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $conditions[] = '`created` ' . $operator . ' ?';
                $params[] = $date . ($key === 'from' ? ' 00:00:00' : ' 23:59:59');
            }
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function sanitize(array $details, int $depth = 0): array
    {
        $clean = [];
        foreach ($details as $key => $value) {
            $key = (string) $key;
            if (preg_match(self::SENSITIVE_KEY, $key) === 1) {
                $clean[$key] = '***';
            } elseif (is_array($value)) {
                $clean[$key] = $depth >= 2 ? '[...]' : self::sanitize($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean[$key] = Redactor::redact($value, 300);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
