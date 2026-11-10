<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Message\Usage;

final class UsageRepository
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function record(string $day, string $scope, string $key, Usage $usage, float $cost): void
    {
        $update = 'UPDATE `AiUsageDaily` SET `requests` = `requests` + 1, `inputTokens` = `inputTokens` + ?, `outputTokens` = `outputTokens` + ?, `cost` = `cost` + ? WHERE `day` = ? AND `scope` = ? AND `scopeKey` = ?';
        $params = [$usage->inputTokens, $usage->outputTokens, $cost, $day, $scope, $key];

        if ($this->db->execute($update, $params) > 0) {
            return;
        }
        try {
            $this->db->execute(
                'INSERT INTO `AiUsageDaily` (`day`, `scope`, `scopeKey`, `requests`, `inputTokens`, `outputTokens`, `cost`) VALUES (?, ?, ?, 1, ?, ?, ?)',
                [$day, $scope, $key, $usage->inputTokens, $usage->outputTokens, $cost],
            );
        } catch (\Throwable) {
            // Паралелна заявка е създала реда първа.
            $this->db->execute($update, $params);
        }
    }

    /**
     * Разходите по обект за интервала, от най-скъпия.
     *
     * @return list<array{key: string, requests: int, inputTokens: int, outputTokens: int, cost: float}>
     */
    public function byScope(string $scope, string $fromDay, string $toDay): array
    {
        $rows = $this->db->fetchAll(
            'SELECT `scopeKey`, SUM(`requests`) AS requests, SUM(`inputTokens`) AS inputTokens, SUM(`outputTokens`) AS outputTokens, SUM(`cost`) AS cost
             FROM `AiUsageDaily` WHERE `scope` = ? AND `day` >= ? AND `day` <= ? GROUP BY `scopeKey` ORDER BY SUM(`cost`) DESC',
            [$scope, $fromDay, $toDay],
        );

        return array_map(static fn (array $r): array => [
            'key' => (string) $r['scopeKey'], 'requests' => (int) $r['requests'], 'inputTokens' => (int) $r['inputTokens'],
            'outputTokens' => (int) $r['outputTokens'], 'cost' => (float) $r['cost'],
        ], $rows);
    }

    /** Разходът в EUR за интервала [от, до] (включително), дати във формат Y-m-d. */
    public function spent(string $scope, string $key, string $fromDay, string $toDay): float
    {
        $row = $this->db->fetchRow(
            'SELECT SUM(`cost`) AS total FROM `AiUsageDaily` WHERE `scope` = ? AND `scopeKey` = ? AND `day` >= ? AND `day` <= ?',
            [$scope, $key, $fromDay, $toDay],
        );

        return (float) ($row['total'] ?? 0);
    }
}
