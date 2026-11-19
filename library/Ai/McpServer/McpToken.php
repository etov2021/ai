<?php

declare(strict_types=1);

namespace Ai\McpServer;

/** Разпознат външен клиент: какво му е позволено. */
final readonly class McpToken
{
    /**
     * @param list<string> $tools позволени инструменти
     * @param ?list<int> $documentIds null = всички документи
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $tools,
        public ?array $documentIds,
        public ?string $agentCode,
        public bool $maskPii,
        public int $rateLimitPerMinute,
    ) {
    }
}
