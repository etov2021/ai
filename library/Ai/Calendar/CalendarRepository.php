<?php

declare(strict_types=1);

namespace Ai\Calendar;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Предложенията за събития и тяхното състояние: pending, working, created, rejected, failed. */
final class CalendarRepository
{
    public const MAX_PENDING = 20;

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function add(string $summary, ?string $description, ?string $location, \DateTimeImmutable $start, \DateTimeImmutable $end, string $timezone, ?string $source = null): int
    {
        // Агент в цикъл не бива да затрупва списъка за потвърждение.
        if ($this->pendingCount() >= self::MAX_PENDING) {
            throw new AiException('Има твърде много непотвърдени предложения. Потребителят трябва първо да ги прегледа.');
        }

        $data = [
            'summary' => mb_substr($summary, 0, 200),
            'description' => $description === null || $description === '' ? null : mb_substr($description, 0, 2000),
            'location' => $location === null || $location === '' ? null : mb_substr($location, 0, 200),
            'startAt' => $start->format('Y-m-d H:i:s'),
            'endAt' => $end->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'status' => 'pending',
            'created' => date('Y-m-d H:i:s'),
        ];
        // Колоната source се пише само при предложение от писмо, за да работи и преди миграция 012.
        if ($source !== null) {
            $data['source'] = $source;
        }
        $this->db->execute(Sql::insert('AiCalendarProposals', array_keys($data)), array_values($data));

        return $this->db->lastInsertId();
    }

    /** Същото събитие от същото писмо не се предлага втори път (напр. при повторен преглед). */
    public function hasSource(string $source, string $summary, \DateTimeImmutable $start): bool
    {
        return $this->db->fetchRow(
            'SELECT `id` FROM `AiCalendarProposals` WHERE `source` = ? AND `summary` = ? AND `startAt` = ?',
            [$source, mb_substr($summary, 0, 200), $start->format('Y-m-d H:i:s')],
        ) !== null;
    }

    public function pendingCount(): int
    {
        return (int) ($this->db->fetchRow('SELECT COUNT(*) AS c FROM `AiCalendarProposals` WHERE `status` = ?', ['pending'])['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiCalendarProposals` WHERE `status` = ? ORDER BY `startAt`', ['pending']);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 20): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiCalendarProposals` WHERE `status` <> ? ORDER BY `decided` DESC, `id` DESC LIMIT ' . max(1, min($limit, 100)), ['pending']);
    }

    /** @return ?array<string, mixed> */
    public function find(int $id): ?array
    {
        return $this->db->fetchRow('SELECT * FROM `AiCalendarProposals` WHERE `id` = ?', [$id]);
    }

    /** Поема предложението атомарно: двойно потвърждение не създава две събития. */
    public function claim(int $id): bool
    {
        return $this->db->execute('UPDATE `AiCalendarProposals` SET `status` = ? WHERE `id` = ? AND `status` = ?', ['working', $id, 'pending']) > 0;
    }

    public function markCreated(int $id, string $link, ?int $userId): void
    {
        $this->db->execute(
            'UPDATE `AiCalendarProposals` SET `status` = ?, `eventLink` = ?, `decided` = ?, `decidedBy` = ?, `error` = NULL WHERE `id` = ?',
            ['created', mb_substr($link, 0, 500), date('Y-m-d H:i:s'), $userId, $id],
        );
    }

    public function markFailed(int $id, string $error, ?int $userId): void
    {
        $this->db->execute(
            'UPDATE `AiCalendarProposals` SET `status` = ?, `error` = ?, `decided` = ?, `decidedBy` = ? WHERE `id` = ?',
            ['failed', mb_substr($error, 0, 255), date('Y-m-d H:i:s'), $userId, $id],
        );
    }

    public function reject(int $id, ?int $userId): bool
    {
        return $this->db->execute(
            'UPDATE `AiCalendarProposals` SET `status` = ?, `decided` = ?, `decidedBy` = ? WHERE `id` = ? AND `status` = ?',
            ['rejected', date('Y-m-d H:i:s'), $userId, $id, 'pending'],
        ) > 0;
    }
}
