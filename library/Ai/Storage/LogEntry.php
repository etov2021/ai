<?php

declare(strict_types=1);

namespace Ai\Storage;

final readonly class LogEntry
{
    public function __construct(
        public string $runId,
        public string $kind,
        public string $status,
        public string $providerCode = '',
        public string $model = '',
        public ?int $agentId = null,
        public ?int $conversationId = null,
        public ?int $userId = null,
        public int $step = 0,
        public ?string $tool = null,
        public ?string $finishReason = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $cost = 0.0,
        public int $durationMs = 0,
        public ?string $error = null,
    ) {
    }
}
