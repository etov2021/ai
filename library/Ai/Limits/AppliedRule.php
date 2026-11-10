<?php

declare(strict_types=1);

namespace Ai\Limits;

/** Правило от AiBudgets, приложено към конкретен обект (напр. агент 3). null означава "без ограничение", 0 - "забранено". */
final readonly class AppliedRule
{
    public function __construct(
        public string $scope,
        public string $key,
        public ?int $rateLimitPerMinute,
        public ?float $dailyLimit,
        public ?float $monthlyLimit,
        public int $warnPercent,
    ) {
    }

    public function label(): string
    {
        return $this->key === '' ? $this->scope : $this->scope . ':' . $this->key;
    }
}
