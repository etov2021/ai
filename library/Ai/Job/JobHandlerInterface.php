<?php

declare(strict_types=1);

namespace Ai\Job;

interface JobHandlerInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return string кратко резултатно съобщение (показва се в списъка със задачи)
     * @throws \Ai\Exception\AiException при грешка; PermanentJobFailure означава "не опитвай пак"
     */
    public function handle(array $payload, Job $job): string;
}
