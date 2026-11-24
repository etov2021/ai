<?php

declare(strict_types=1);

namespace Ai\Job;

final readonly class Job
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $id,
        public string $type,
        public array $payload,
        public int $attempts,
        public int $maxAttempts,
        public ?int $userId,
        public ?int $automationId,
    ) {
    }
}
