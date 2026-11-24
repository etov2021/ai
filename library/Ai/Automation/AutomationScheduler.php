<?php

declare(strict_types=1);

namespace Ai\Automation;

use Ai\Audit\AuditLog;
use Ai\Job\JobRepository;

/** Превръща настъпилите разписания в задачи за опашката. Самото изпълнение е работа на JobWorker. */
final class AutomationScheduler
{
    public function __construct(
        private AutomationRepository $automations,
        private JobRepository $jobs,
        private \Closure $clock,
        private ?AuditLog $audit = null,
    ) {
    }

    /** @return int брой създадени задачи */
    public function tick(): int
    {
        $created = 0;

        foreach ($this->automations->due() as $row) {
            $id = (int) $row['id'];

            try {
                $next = Schedule::fromRow($row)->next(($this->clock)());
            } catch (\Throwable) {
                // Повредено разписание не бива да блокира останалите: автоматизацията се изключва.
                $this->automations->setActive($id, false);
                continue;
            }

            // Първо се премества времето: при срив няма двойно изпълнение, а при два планировчика печели един.
            // Пропуснатите по време на спиране изпълнения не се наваксват: стартира се най-много едно.
            if (!$this->automations->advance($id, (string) $row['nextRun'], $next)) {
                continue;
            }

            // Предишното изпълнение още не е свършило: пропуска се, за да не се натрупват.
            if ($this->jobs->hasUnfinished($id)) {
                $this->audit?->record('automation.skipped', 'automation', (string) $id, ['reason' => 'previous run unfinished'], 'denied');
                continue;
            }

            $jobId = $this->enqueue($row);
            $this->automations->recordRun($id, $jobId);
            $created++;
        }

        return $created;
    }

    /** Веднага, извън разписанието ("изпълни сега"). Не променя следващото планирано време. */
    public function runNow(int $id, ?int $userId = null): ?int
    {
        $row = $this->automations->find($id);
        if ($row === null || $this->jobs->hasUnfinished($id)) {
            return null;
        }

        $jobId = $this->enqueue($row, $userId);
        $this->automations->recordRun($id, $jobId);

        return $jobId;
    }

    /** @param array<string, mixed> $row */
    private function enqueue(array $row, ?int $userId = null): int
    {
        $kind = (string) $row['kind'];

        return $this->jobs->enqueue($kind, [$kind => (string) $row['target'], 'input' => (string) $row['input']], $userId, (int) $row['id']);
    }
}
