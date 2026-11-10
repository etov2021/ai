<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Message\ChatResponse;

/** Предава събитията на няколко наблюдатели; един развален не пречи на останалите. */
final class CompositeObserver implements RunObserver
{
    /** @var list<RunObserver> */
    private array $observers;

    public function __construct(RunObserver ...$observers)
    {
        $this->observers = array_values($observers);
    }

    public function llmCall(int $step, ChatResponse $response, int $durationMs): void
    {
        $this->each(static fn (RunObserver $o) => $o->llmCall($step, $response, $durationMs));
    }

    public function llmFailed(int $step, \Throwable $error, int $durationMs): void
    {
        $this->each(static fn (RunObserver $o) => $o->llmFailed($step, $error, $durationMs));
    }

    public function toolRun(int $step, ToolRun $run): void
    {
        $this->each(static fn (RunObserver $o) => $o->toolRun($step, $run));
    }

    public function finished(RunResult $result): void
    {
        $this->each(static fn (RunObserver $o) => $o->finished($result));
    }

    private function each(\Closure $call): void
    {
        foreach ($this->observers as $observer) {
            try {
                $call($observer);
            } catch (\Throwable) {
            }
        }
    }
}
