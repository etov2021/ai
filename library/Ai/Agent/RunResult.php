<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Message\Message;
use Ai\Message\Usage;

final readonly class RunResult
{
    /**
     * @param list<Message> $messages новите съобщения от това изпълнение (без подадената история)
     * @param list<StepResult> $steps
     */
    public function __construct(
        public RunStatus $status,
        public ?string $content,
        public array $messages,
        public array $steps,
        public Usage $usage,
        public int $durationMs,
        public float $cost = 0.0,
    ) {
    }

    public function withCost(float $cost): self
    {
        return new self($this->status, $this->content, $this->messages, $this->steps, $this->usage, $this->durationMs, $cost);
    }

    /** Обединява с предходен опит: съобщенията и съдържанието са на текущия, а токените, разходът и времето са сума. */
    public function withPriorAttempt(self $prior): self
    {
        return new self(
            $this->status,
            $this->content,
            $this->messages,
            [...$prior->steps, ...$this->steps],
            new Usage($prior->usage->inputTokens + $this->usage->inputTokens, $prior->usage->outputTokens + $this->usage->outputTokens),
            $prior->durationMs + $this->durationMs,
            $prior->cost + $this->cost,
        );
    }
}
