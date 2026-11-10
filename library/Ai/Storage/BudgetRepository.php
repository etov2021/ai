<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Agent\Agent;
use Ai\Exception\AiException;
use Ai\Limits\AppliedRule;

/**
 * Правила за лимити по обхват: global, provider, agent, user.
 * Ключ "*" е правило по подразбиране за всеки обект от обхвата; конкретен ключ го замества.
 */
final class BudgetRepository
{
    public const SCOPES = ['global', 'provider', 'agent', 'user'];
    private const TABLE = 'AiBudgets';

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function save(
        string $scope,
        string $scopeKey,
        ?int $rateLimitPerMinute = null,
        ?float $dailyLimit = null,
        ?float $monthlyLimit = null,
        int $warnPercent = 80,
        bool $active = true,
    ): void {
        if (!in_array($scope, self::SCOPES, true)) {
            throw new AiException('Непознат обхват на правилото.');
        }
        if ($scope === 'global') {
            $scopeKey = '';
        } elseif (preg_match('/^(\*|[A-Za-z0-9_-]{1,40})$/', $scopeKey) !== 1) {
            throw new AiException('Ключът трябва да е "*" или идентификатор до 40 знака.');
        }
        if ($rateLimitPerMinute !== null && ($rateLimitPerMinute < 0 || $rateLimitPerMinute > 100000)) {
            throw new AiException('Лимитът на заявките трябва да е между 0 и 100000.');
        }
        foreach ([$dailyLimit, $monthlyLimit] as $limit) {
            if ($limit !== null && ($limit < 0 || $limit > 1_000_000)) {
                throw new AiException('Бюджетът трябва да е между 0 и 1000000.');
            }
        }
        if ($warnPercent < 1 || $warnPercent > 100) {
            throw new AiException('Прагът за предупреждение трябва да е между 1 и 100.');
        }

        $data = [
            'rateLimitPerMinute' => $rateLimitPerMinute, 'dailyLimit' => $dailyLimit, 'monthlyLimit' => $monthlyLimit,
            'warnPercent' => $warnPercent, 'active' => $active ? 1 : 0, 'updated' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->db->fetchRow('SELECT `id` FROM `AiBudgets` WHERE `scope` = ? AND `scopeKey` = ?', [$scope, $scopeKey]);
        if ($existing === null) {
            $data += ['scope' => $scope, 'scopeKey' => $scopeKey];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
        } else {
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'id'), [...array_values($data), (int) $existing['id']]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiBudgets` ORDER BY `scope`, `scopeKey`');
    }

    /** @return list<AppliedRule> */
    public function applicable(Agent $agent, ?int $userId): array
    {
        $rules = [];
        foreach ($this->db->fetchAll('SELECT * FROM `AiBudgets` WHERE `active` = 1') as $row) {
            $rules[(string) $row['scope']][(string) $row['scopeKey']] = $row;
        }

        $subjects = ['global' => '', 'provider' => $agent->providerCode, 'agent' => (string) $agent->id];
        if ($userId !== null) {
            $subjects['user'] = (string) $userId;
        }

        $applied = [];
        foreach ($subjects as $scope => $key) {
            $row = $rules[$scope][$key] ?? ($scope === 'global' ? null : ($rules[$scope]['*'] ?? null));
            if ($row !== null) {
                $applied[] = new AppliedRule(
                    $scope,
                    $key,
                    $row['rateLimitPerMinute'] !== null ? (int) $row['rateLimitPerMinute'] : null,
                    $row['dailyLimit'] !== null ? (float) $row['dailyLimit'] : null,
                    $row['monthlyLimit'] !== null ? (float) $row['monthlyLimit'] : null,
                    (int) $row['warnPercent'],
                );
            }
        }

        return $applied;
    }
}
