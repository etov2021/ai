<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Message\ChatResponse;

/** Получава събитията на изпълнението; грешка в наблюдателя не прекъсва агента. */
interface RunObserver
{
    public function llmCall(int $step, ChatResponse $response, int $durationMs): void;

    public function llmFailed(int $step, \Throwable $error, int $durationMs): void;

    public function toolRun(int $step, ToolRun $run): void;

    public function finished(RunResult $result): void;
}
