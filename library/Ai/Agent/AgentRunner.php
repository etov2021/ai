<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Contract\ProviderInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\ToolException;
use Ai\Message\ChatRequest;
use Ai\Message\FinishReason;
use Ai\Message\Message;
use Ai\Message\ToolCall;
use Ai\Message\Usage;
use Ai\Tool\SchemaValidator;

/** Цикълът модел -> инструменти -> модел с твърди ограничения за стъпки, време и брой извиквания. */
final class AgentRunner
{
    private const MAX_TOOL_CALLS_PER_STEP = 5;
    private const MAX_RESULT_CHARS = 20000;

    /** @var \Closure(): float */
    private \Closure $clock;

    /**
     * @param ?\Closure(): float $clock секунди с дробна част; подменя се в тестове
     * @param ?\Closure(int): void $beforeStep извиква се преди всяка заявка към модела; изключение спира изпълнението
     */
    public function __construct(
        private ProviderInterface $provider,
        ?\Closure $clock = null,
        private ?RunObserver $observer = null,
        private ?\Closure $beforeStep = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /**
     * @param list<ToolInterface> $tools единственото, което моделът може да извика
     * @param list<Message> $messages историята, завършваща със съобщение на потребителя
     */
    public function run(Agent $agent, array $tools, array $messages): RunResult
    {
        $started = ($this->clock)();

        $byName = [];
        foreach ($tools as $tool) {
            $byName[$tool->definition()->name] = $tool;
        }
        $definitions = array_values(array_map(static fn (ToolInterface $t) => $t->definition(), $byName));

        $new = [];
        $steps = [];
        $input = $output = 0;

        for ($step = 1; ; $step++) {
            // Времето се проверява между стъпките; отделна заявка към доставчика се ограничава от неговия таймаут.
            if ($step > 1 && ($this->clock)() - $started >= $agent->timeoutSeconds) {
                return $this->result(RunStatus::Timeout, null, $new, $steps, $input, $output, $started);
            }

            $stepStarted = ($this->clock)();
            try {
                if ($this->beforeStep !== null) {
                    ($this->beforeStep)($step);
                }
                $response = $this->provider->chat(new ChatRequest(
                    $agent->model,
                    [...$messages, ...$new],
                    $agent->systemPrompt,
                    $definitions,
                    $agent->temperature,
                    $agent->maxTokens,
                ));
            } catch (\Throwable $e) {
                $this->notify(fn (RunObserver $o) => $o->llmFailed($step, $e, $this->ms($stepStarted)));
                throw $e;
            }
            $this->notify(fn (RunObserver $o) => $o->llmCall($step, $response, $this->ms($stepStarted)));
            $input += $response->usage->inputTokens;
            $output += $response->usage->outputTokens;

            if (!$response->hasToolCalls()) {
                $new[] = $response->toMessage();
                $steps[] = new StepResult($response, [], $this->ms($stepStarted));
                $status = match ($response->finishReason) {
                    FinishReason::Length => RunStatus::Truncated,
                    FinishReason::ContentFilter => RunStatus::Blocked,
                    default => RunStatus::Completed,
                };

                return $this->result($status, $response->content, $new, $steps, $input, $output, $started);
            }

            if ($step >= $agent->maxSteps) {
                // Съобщение с неотговорени tool calls прави историята невалидна за доставчиците, затова не се добавя.
                $steps[] = new StepResult($response, [], $this->ms($stepStarted));

                return $this->result(RunStatus::MaxSteps, $response->content, $new, $steps, $input, $output, $started);
            }

            $new[] = $response->toMessage();
            $runs = [];
            foreach ($response->toolCalls as $i => $call) {
                $run = $this->executeTool($call, $byName, $i >= self::MAX_TOOL_CALLS_PER_STEP);
                $this->notify(fn (RunObserver $o) => $o->toolRun($step, $run));
                $runs[] = $run;
                // Всяко tool call получава отговор, иначе следващата заявка се отхвърля от доставчика.
                $new[] = Message::toolResult($call->id, $call->name, $run->ok ? $run->result : 'Грешка: ' . $run->result);
            }
            $steps[] = new StepResult($response, $runs, $this->ms($stepStarted));
        }
    }

    /** @param array<string, ToolInterface> $byName */
    private function executeTool(ToolCall $call, array $byName, bool $overLimit): ToolRun
    {
        $started = ($this->clock)();
        $fail = fn (string $message): ToolRun => new ToolRun($call->id, $call->name, $call->arguments, false, $message, $this->ms($started));

        if ($overLimit) {
            return $fail('Прекалено много извиквания в една стъпка.');
        }
        if ($call->parseError !== null) {
            return $fail($call->parseError);
        }
        // Моделът може да измисли име; изпълняват се само инструменти, разрешени за този агент.
        $tool = $byName[$call->name] ?? null;
        if ($tool === null) {
            return $fail('Инструментът не е разрешен.');
        }

        $errors = SchemaValidator::validate($tool->definition()->parameters, $call->arguments);
        if ($errors !== []) {
            return $fail('Невалидни аргументи: ' . implode('; ', array_slice($errors, 0, 5)));
        }

        try {
            $result = mb_substr($tool->execute($call->arguments), 0, self::MAX_RESULT_CHARS);
        } catch (ToolException $e) {
            return $fail($e->getMessage());
        } catch (\Throwable) {
            // Вътрешните грешки не се връщат на модела; stage 4 ще ги логва.
            return $fail('Инструментът е недостъпен.');
        }

        return new ToolRun($call->id, $call->name, $call->arguments, true, $result, $this->ms($started));
    }

    private function ms(float $since): int
    {
        return (int) round((($this->clock)() - $since) * 1000);
    }

    /**
     * @param list<Message> $new
     * @param list<StepResult> $steps
     */
    private function result(RunStatus $status, ?string $content, array $new, array $steps, int $input, int $output, float $started): RunResult
    {
        $result = new RunResult($status, $content, $new, $steps, new Usage($input, $output), $this->ms($started));
        $this->notify(fn (RunObserver $o) => $o->finished($result));

        return $result;
    }

    // Грешка при логване не бива да спира или променя изпълнението.
    private function notify(\Closure $call): void
    {
        if ($this->observer === null) {
            return;
        }
        try {
            $call($this->observer);
        } catch (\Throwable) {
        }
    }
}
