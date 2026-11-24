<?php

declare(strict_types=1);

namespace Ai\Job;

/** Обвива готова функция като обработчик: за вече съществуващи операции (индексиране, поща). */
final class ClosureJobHandler implements JobHandlerInterface
{
    /** @param \Closure(array<string, mixed>, Job): string $fn */
    public function __construct(private \Closure $fn)
    {
    }

    public function handle(array $payload, Job $job): string
    {
        return ($this->fn)($payload, $job);
    }
}
