<?php

declare(strict_types=1);

namespace Ai\Job;

use Ai\Audit\AuditLog;

/** Изпълнява готовите задачи една след друга в рамките на зададено време (за един cron цикъл). */
final class JobWorker
{
    /**
     * @param array<string, JobHandlerInterface> $handlers тип => обработчик
     * @param \Closure(): int $clock
     */
    public function __construct(
        private JobRepository $jobs,
        private array $handlers,
        private \Closure $clock,
        private ?AuditLog $audit = null,
    ) {
    }

    /**
     * Нова задача се започва само докато не е изтекъл бюджетът от време; една започната задача не се прекъсва.
     *
     * @return array{ran: int, done: int, failed: int, retried: int}
     */
    public function run(int $maxJobs = 20, int $maxSeconds = 50): array
    {
        $stats = ['ran' => 0, 'done' => 0, 'failed' => 0, 'retried' => 0];
        $deadline = ($this->clock)() + max(1, $maxSeconds);

        while ($stats['ran'] < $maxJobs && ($this->clock)() < $deadline) {
            $job = $this->jobs->claim();
            if ($job === null) {
                break;
            }
            $stats['ran']++;

            $handler = $this->handlers[$job->type] ?? null;
            if ($handler === null) {
                $this->jobs->fail($job->id, 'Непознат тип задача: ' . $job->type, true);
                $stats['failed']++;
                $this->record($job, 'error', ['error' => 'unknown type']);
                continue;
            }

            try {
                $this->jobs->complete($job->id, $handler->handle($job->payload, $job));
                $stats['done']++;
                $this->record($job, 'ok');
            } catch (\Throwable $e) {
                $permanent = $e instanceof PermanentJobFailure;
                $this->jobs->fail($job->id, $e->getMessage(), $permanent);
                $final = $permanent || $job->attempts >= $job->maxAttempts;
                $stats[$final ? 'failed' : 'retried']++;
                $this->record($job, 'error', ['error' => $e->getMessage(), 'final' => $final]);
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $details */
    private function record(Job $job, string $result, array $details = []): void
    {
        $this->audit?->record('job.run', 'job', (string) $job->id, ['type' => $job->type, 'attempt' => $job->attempts, 'automationId' => $job->automationId] + $details, $result);
    }
}
