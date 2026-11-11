<?php

declare(strict_types=1);

namespace Ai\Workflow;

final readonly class WorkflowResult
{
    /**
     * @param array<string, string> $steps изход на всяка стъпка по id
     * @param list<string> $warnings
     */
    public function __construct(
        public string $output,
        public array $steps,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public int $durationMs,
        public int $calls,
        public array $warnings = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'steps' => $this->steps,
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'cost' => $this->cost,
            'durationMs' => $this->durationMs,
            'calls' => $this->calls,
            'warnings' => $this->warnings,
        ];
    }
}
