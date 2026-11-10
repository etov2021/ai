<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Message\ChatResponse;

/** Една заявка към модела и изпълнените след нея инструменти. */
final readonly class StepResult
{
    /** @param list<ToolRun> $toolRuns */
    public function __construct(public ChatResponse $response, public array $toolRuns = [], public int $durationMs = 0)
    {
    }
}
