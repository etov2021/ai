<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Message\ChatResponse;
use Ai\Pricing\CostCalculator;
use Ai\Security\Redactor;
use Ai\Storage\LogEntry;
use Ai\Storage\RequestLogRepository;

/**
 * Записва метаданни за всяка заявка към модела и всеки инструмент.
 * Съдържанието на съобщенията и аргументите на инструментите умишлено не се логват: може да са лични данни.
 */
final class RunLogger implements RunObserver
{
    public readonly string $runId;

    public function __construct(
        private RequestLogRepository $log,
        private Agent $agent,
        private ?int $conversationId = null,
        private ?int $userId = null,
        ?string $runId = null,
    ) {
        $this->runId = $runId ?? bin2hex(random_bytes(16));
    }

    public function llmCall(int $step, ChatResponse $response, int $durationMs): void
    {
        $this->write(new LogEntry(
            $this->runId, 'llm', 'ok',
            step: $step,
            finishReason: $response->finishReason->value,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            cost: CostCalculator::cost($response->usage, $this->agent->inputPricePerMillion, $this->agent->outputPricePerMillion),
            durationMs: $durationMs,
        ));
    }

    public function llmFailed(int $step, \Throwable $error, int $durationMs): void
    {
        $this->write(new LogEntry($this->runId, 'llm', 'error', step: $step, durationMs: $durationMs, error: Redactor::redact($error->getMessage())));
    }

    public function toolRun(int $step, ToolRun $run): void
    {
        $this->write(new LogEntry(
            $this->runId, 'tool', $run->ok ? 'ok' : 'error',
            step: $step,
            tool: $run->name,
            durationMs: $run->durationMs,
            error: $run->ok ? null : Redactor::redact($run->result),
        ));
    }

    // Редът за изпълнението няма токени и цена, за да не се броят два пъти при сумиране по kind = 'llm'.
    public function finished(RunResult $result): void
    {
        $this->write(new LogEntry($this->runId, 'run', $result->status->value, step: count($result->steps), durationMs: $result->durationMs));
    }

    /** Бележка за изпълнението (отхвърлено, предупреждение) без токени и цена. */
    public function note(string $status, string $message): void
    {
        $this->write(new LogEntry($this->runId, 'run', $status, error: Redactor::redact($message)));
    }

    private function write(LogEntry $e): void
    {
        $this->log->add(new LogEntry(
            $e->runId, $e->kind, $e->status, $this->agent->providerCode, $this->agent->model, $this->agent->id,
            $this->conversationId, $this->userId, $e->step, $e->tool, $e->finishReason,
            $e->inputTokens, $e->outputTokens, $e->cost, $e->durationMs, $e->error,
        ));
    }
}
