<?php

declare(strict_types=1);

namespace Ai\Automation;

use Ai\Exception\AiException;

/** Разписание: на всеки N минути, всеки ден в час или в избрани дни от седмицата (1 = понеделник ... 7 = неделя). Часът е по времето на сървъра. */
final readonly class Schedule
{
    public const EVERY = 'every';
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';

    // По-често от 5 минути е по-вероятно грешка, отколкото замисъл (всяко изпълнение струва пари).
    public const MIN_INTERVAL_MINUTES = 5;
    public const MAX_INTERVAL_MINUTES = 10080;

    /** @param list<int> $weekdays */
    private function __construct(public string $type, public ?int $intervalMinutes, public ?string $time, public array $weekdays)
    {
    }

    public static function every(int $minutes): self
    {
        if ($minutes < self::MIN_INTERVAL_MINUTES || $minutes > self::MAX_INTERVAL_MINUTES) {
            throw new AiException(sprintf('Интервалът трябва да е между %d и %d минути.', self::MIN_INTERVAL_MINUTES, self::MAX_INTERVAL_MINUTES));
        }

        return new self(self::EVERY, $minutes, null, []);
    }

    public static function daily(string $time): self
    {
        return new self(self::DAILY, null, self::time($time), []);
    }

    /** @param list<int> $weekdays */
    public static function weekly(string $time, array $weekdays): self
    {
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        sort($weekdays);
        if ($weekdays === [] || array_diff($weekdays, [1, 2, 3, 4, 5, 6, 7]) !== []) {
            throw new AiException('Изберете поне един ден от седмицата.');
        }

        return new self(self::WEEKLY, null, self::time($time), $weekdays);
    }

    /** @param array<string, mixed> $row ред от AiAutomations */
    public static function fromRow(array $row): self
    {
        return match ((string) $row['scheduleType']) {
            self::EVERY => self::every((int) $row['intervalMinutes']),
            self::DAILY => self::daily((string) $row['scheduleTime']),
            self::WEEKLY => self::weekly((string) $row['scheduleTime'], array_filter(explode(',', (string) $row['weekdays']), 'strlen')),
            default => throw new AiException('Непознат тип разписание.'),
        };
    }

    /** Първият момент строго след $after (unix време). */
    public function next(int $after): int
    {
        if ($this->type === self::EVERY) {
            return $after + (int) $this->intervalMinutes * 60;
        }

        [$hour, $minute] = array_map('intval', explode(':', (string) $this->time));
        $day = (new \DateTimeImmutable('@' . $after))->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        // До 8 дни: най-далечният случай е един ден в седмицата, пропуснат току-що.
        for ($i = 0; $i < 8; $i++) {
            $candidate = $day->modify('+' . $i . ' day')->setTime($hour, $minute);
            if ($candidate->getTimestamp() > $after && ($this->type === self::DAILY || in_array((int) $candidate->format('N'), $this->weekdays, true))) {
                return $candidate->getTimestamp();
            }
        }

        throw new AiException('Не може да се изчисли следващо изпълнение.');
    }

    public function describe(): string
    {
        $names = [1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб', 7 => 'нд'];

        return match ($this->type) {
            self::EVERY => 'на всеки ' . $this->intervalMinutes . ' мин',
            self::DAILY => 'всеки ден в ' . $this->time,
            default => implode(', ', array_map(static fn (int $d): string => $names[$d], $this->weekdays)) . ' в ' . $this->time,
        };
    }

    private static function time(string $time): string
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time) !== 1) {
            throw new AiException('Часът трябва да е във формат ЧЧ:ММ.');
        }

        return $time;
    }
}
