<?php

declare(strict_types=1);

namespace Ai\Automation;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Планирани автоматизации: "агент или работен поток + вход + разписание". */
final class AutomationRepository
{
    public const KINDS = ['agent', 'workflow'];
    private const MAX_INPUT_CHARS = 5000;

    /** @param \Closure(): int $clock */
    public function __construct(private DatabaseInterface $db, private \Closure $clock)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiAutomations` ORDER BY `name`');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchRow('SELECT * FROM `AiAutomations` WHERE `id` = ?', [$id]);
    }

    /** @throws AiException */
    public function save(?int $id, string $name, string $kind, string $target, string $input, Schedule $schedule, bool $active, ?int $userId): int
    {
        $name = trim($name);
        $input = trim($input);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително (до 100 знака).');
        }
        if (!in_array($kind, self::KINDS, true) || preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $target) !== 1) {
            throw new AiException('Изберете агент или работен поток.');
        }
        if ($input === '' || mb_strlen($input) > self::MAX_INPUT_CHARS) {
            throw new AiException(sprintf('Входът е задължителен (до %d знака).', self::MAX_INPUT_CHARS));
        }
        if ($id !== null && $this->find($id) === null) {
            throw new AiException('Автоматизацията не е намерена.');
        }

        $now = ($this->clock)();
        $data = [
            'name' => $name,
            'kind' => $kind,
            'target' => $target,
            'input' => $input,
            'scheduleType' => $schedule->type,
            'intervalMinutes' => $schedule->intervalMinutes,
            'scheduleTime' => $schedule->time,
            'weekdays' => $schedule->weekdays === [] ? null : implode(',', $schedule->weekdays),
            'active' => $active ? 1 : 0,
            // След всяка промяна се брои наново от сега: старото време може да е по друго разписание.
            'nextRun' => $active ? date('Y-m-d H:i:s', $schedule->next($now)) : null,
            'updated' => date('Y-m-d H:i:s', $now),
        ];

        if ($id === null) {
            $data['createdBy'] = $userId;
            $data['created'] = $data['updated'];
            $this->db->execute(Sql::insert('AiAutomations', array_keys($data)), array_values($data));

            return $this->db->lastInsertId();
        }

        $this->db->execute(Sql::update('AiAutomations', array_keys($data), 'id'), [...array_values($data), $id]);

        return $id;
    }

    public function setActive(int $id, bool $active): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }

        $now = ($this->clock)();
        $next = $active ? date('Y-m-d H:i:s', Schedule::fromRow($row)->next($now)) : null;
        $this->db->execute('UPDATE `AiAutomations` SET `active` = ?, `nextRun` = ?, `updated` = ? WHERE `id` = ?', [$active ? 1 : 0, $next, date('Y-m-d H:i:s', $now), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiAutomations` WHERE `id` = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function due(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `AiAutomations` WHERE `active` = 1 AND `nextRun` IS NOT NULL AND `nextRun` <= ? ORDER BY `nextRun`',
            [date('Y-m-d H:i:s', ($this->clock)())],
        );
    }

    /** Премества nextRun само ако още е старото: от два едновременни планировчика печели един. */
    public function advance(int $id, string $expectedNextRun, int $newNextRun): bool
    {
        return $this->db->execute(
            'UPDATE `AiAutomations` SET `nextRun` = ? WHERE `id` = ? AND `nextRun` = ?',
            [date('Y-m-d H:i:s', $newNextRun), $id, $expectedNextRun],
        ) === 1;
    }

    public function recordRun(int $id, int $jobId): void
    {
        $this->db->execute('UPDATE `AiAutomations` SET `lastRun` = ?, `lastJobId` = ? WHERE `id` = ?', [date('Y-m-d H:i:s', ($this->clock)()), $jobId, $id]);
    }
}
