<?php

declare(strict_types=1);

namespace Ai\Job;

use Ai\Exception\AiException;
use Ai\Security\Redactor;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Опашката със задачи. Състояния: pending, running, done, failed, cancelled. */
final class JobRepository
{
    public const STATUSES = ['pending', 'running', 'done', 'failed', 'cancelled'];

    /**
     * @param \Closure(): int $clock
     * @param int $lockSeconds след това време "running" задача се счита за изоставена (процесът е умрял) и се взима отново
     */
    public function __construct(private DatabaseInterface $db, private \Closure $clock, private int $lockSeconds = 900)
    {
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(string $type, array $payload, ?int $userId = null, ?int $automationId = null, int $delaySeconds = 0, int $maxAttempts = 3): int
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,29}$/', $type) !== 1) {
            throw new AiException('Невалиден тип задача.');
        }

        $now = ($this->clock)();
        $data = [
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'status' => 'pending',
            'maxAttempts' => max(1, min($maxAttempts, 10)),
            'runAfter' => $this->at($now + max(0, $delaySeconds)),
            'userId' => $userId,
            'automationId' => $automationId,
            'created' => $this->at($now),
        ];
        $this->db->execute(Sql::insert('AiJobs', array_keys($data)), array_values($data));

        return $this->db->lastInsertId();
    }

    /** Взима следващата готова задача; два работника никога не вземат една и съща. */
    public function claim(): ?Job
    {
        $now = $this->at(($this->clock)());

        // Изоставена задача, която е изчерпала опитите си, не се върти безкрайно.
        $this->db->execute(
            'UPDATE `AiJobs` SET `status` = \'failed\', `error` = ?, `finished` = ? WHERE `status` = \'running\' AND `lockedUntil` < ? AND `attempts` >= `maxAttempts`',
            ['Прекъсната: изтекло е времето за изпълнение.', $now, $now],
        );

        for ($try = 0; $try < 3; $try++) {
            $row = $this->db->fetchRow(
                'SELECT `id` FROM `AiJobs` WHERE (`status` = \'pending\' AND `runAfter` <= ?) OR (`status` = \'running\' AND `lockedUntil` < ?) ORDER BY `runAfter`, `id` LIMIT 1',
                [$now, $now],
            );
            if ($row === null) {
                return null;
            }

            $id = (int) $row['id'];
            // Условието се повтаря в UPDATE: печели само един работник, останалите виждат 0 променени реда.
            $taken = $this->db->execute(
                'UPDATE `AiJobs` SET `status` = \'running\', `attempts` = `attempts` + 1, `lockedUntil` = ?, `started` = ? WHERE `id` = ? AND ((`status` = \'pending\' AND `runAfter` <= ?) OR (`status` = \'running\' AND `lockedUntil` < ?))',
                [$this->at(($this->clock)() + $this->lockSeconds), $now, $id, $now, $now],
            );
            if ($taken === 1) {
                return $this->find($id);
            }
        }

        return null;
    }

    public function complete(int $id, string $result): void
    {
        $this->db->execute(
            'UPDATE `AiJobs` SET `status` = \'done\', `result` = ?, `error` = NULL, `lockedUntil` = NULL, `finished` = ? WHERE `id` = ? AND `status` = \'running\'',
            [mb_substr($result, 0, 4000), $this->at(($this->clock)()), $id],
        );
    }

    /** Връща в опашката със закъснение (60 с, 2 мин, 4 мин, ...; най-много час), а изчерпаните опити го правят окончателно неуспешно. */
    public function fail(int $id, string $error, bool $permanent = false): void
    {
        $row = $this->db->fetchRow('SELECT `attempts`, `maxAttempts` FROM `AiJobs` WHERE `id` = ?', [$id]);
        if ($row === null) {
            return;
        }

        $now = ($this->clock)();
        $error = Redactor::redact($error, 500);
        $attempts = (int) $row['attempts'];

        if ($permanent || $attempts >= (int) $row['maxAttempts']) {
            $this->db->execute(
                'UPDATE `AiJobs` SET `status` = \'failed\', `error` = ?, `lockedUntil` = NULL, `finished` = ? WHERE `id` = ? AND `status` = \'running\'',
                [$error, $this->at($now), $id],
            );

            return;
        }

        $delay = min(3600, 60 * 2 ** max(0, $attempts - 1));
        $this->db->execute(
            'UPDATE `AiJobs` SET `status` = \'pending\', `error` = ?, `lockedUntil` = NULL, `runAfter` = ? WHERE `id` = ? AND `status` = \'running\'',
            [$error, $this->at($now + $delay), $id],
        );
    }

    /** Само чакаща задача може да се отмени: работеща не се прекъсва отвън. */
    public function cancel(int $id): bool
    {
        return $this->db->execute(
            'UPDATE `AiJobs` SET `status` = \'cancelled\', `finished` = ? WHERE `id` = ? AND `status` = \'pending\'',
            [$this->at(($this->clock)()), $id],
        ) === 1;
    }

    public function retry(int $id): bool
    {
        return $this->db->execute(
            'UPDATE `AiJobs` SET `status` = \'pending\', `attempts` = 0, `error` = NULL, `result` = NULL, `finished` = NULL, `runAfter` = ? WHERE `id` = ? AND `status` IN (\'failed\', \'cancelled\')',
            [$this->at(($this->clock)()), $id],
        ) === 1;
    }

    public function find(int $id): ?Job
    {
        $row = $this->db->fetchRow('SELECT `id`, `type`, `payload`, `attempts`, `maxAttempts`, `userId`, `automationId` FROM `AiJobs` WHERE `id` = ?', [$id]);
        if ($row === null) {
            return null;
        }

        $payload = json_decode((string) $row['payload'], true);

        return new Job(
            (int) $row['id'],
            (string) $row['type'],
            is_array($payload) ? $payload : [],
            (int) $row['attempts'],
            (int) $row['maxAttempts'],
            $row['userId'] === null ? null : (int) $row['userId'],
            $row['automationId'] === null ? null : (int) $row['automationId'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50, ?string $status = null, int $offset = 0): array
    {
        $where = '';
        $params = [];
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $where = ' WHERE `status` = ?';
            $params[] = $status;
        }

        return $this->db->fetchAll(
            'SELECT `id`, `type`, `payload`, `status`, `attempts`, `maxAttempts`, `runAfter`, `automationId`, `result`, `error`, `created`, `started`, `finished` FROM `AiJobs`'
            . $where . ' ORDER BY `id` DESC LIMIT ' . max(1, min($limit, 200)) . ' OFFSET ' . max(0, $offset),
            $params,
        );
    }

    /** @return array<string, int> брой по състояние (всички състояния присъстват) */
    public function counts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->fetchAll('SELECT `status`, COUNT(*) AS n FROM `AiJobs` GROUP BY `status`') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Има ли за автоматизацията задача, която още чака или работи (за да не се трупат застъпващи се изпълнения). */
    public function hasUnfinished(int $automationId): bool
    {
        return $this->db->fetchRow('SELECT `id` FROM `AiJobs` WHERE `automationId` = ? AND `status` IN (\'pending\', \'running\') LIMIT 1', [$automationId]) !== null;
    }

    public function purge(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM `AiJobs` WHERE `status` IN (\'done\', \'failed\', \'cancelled\') AND `finished` < ?',
            [$this->at(($this->clock)() - max(1, $days) * 86400)],
        );
    }

    private function at(int $time): string
    {
        return date('Y-m-d H:i:s', $time);
    }
}
