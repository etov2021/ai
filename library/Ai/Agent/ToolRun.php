<?php

declare(strict_types=1);

namespace Ai\Agent;

final readonly class ToolRun
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $callId,
        public string $name,
        public array $arguments,
        public bool $ok,
        public string $result,
        public int $durationMs,
    ) {
    }
}
