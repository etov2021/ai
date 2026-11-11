<?php

declare(strict_types=1);

namespace Ai\Workflow;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Contract\WorkflowInterface;
use Ai\Exception\AiException;
use Ai\Exception\LimitException;

/**
 * Изпълнява WorkflowDefinition стъпка по стъпка през AgentService, така че лимитите, бюджетите и логовете на агентите важат и тук.
 * Не е за повторно влизане: броячите се нулират при всяко изпълнение.
 */
final class WorkflowRunner implements WorkflowInterface
{
    public const APPROVED_MARKER = '[[OK]]';

    // Рецензентът е обикновен агент със собствени инструкции; маркерът му се иска в съобщението, за да не се променя системният му промпт.
    private const REVIEW_HINT = 'Ако черновата е приемлива, започни отговора с ' . self::APPROVED_MARKER . '. Иначе опиши конкретно какво да се поправи.';

    private int $calls = 0;
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private float $cost = 0.0;

    public function __construct(
        private AgentService $agents,
        private WorkflowDefinition $definition,
        private ?int $userId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input очаква се ключ "input" с текст
     * @return array<string, mixed>
     */
    public function run(array $input): array
    {
        $text = $input['input'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new AiException('Липсва входен текст.');
        }

        return $this->execute($text)->toArray();
    }

    public function execute(string $input): WorkflowResult
    {
        $started = hrtime(true);
        $this->calls = $this->inputTokens = $this->outputTokens = 0;
        $this->cost = 0.0;

        $values = ['input' => $input];
        $outputs = [];
        $warnings = [];
        $last = '';

        foreach ($this->definition->steps as $step) {
            $id = $step['id'];
            $last = match ($step['type']) {
                'agent' => $this->call($id, $step['agent'], $this->render($step['input'], $values)),
                'router' => $this->route($step, $values, $warnings),
                'loop' => $this->loop($step, $values, $warnings),
            };
            $values[$id] = $outputs[$id] = $last;
        }

        return new WorkflowResult(
            $last,
            $outputs,
            $this->inputTokens,
            $this->outputTokens,
            $this->cost,
            (int) ((hrtime(true) - $started) / 1_000_000),
            $this->calls,
            $warnings,
        );
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $values
     * @param list<string> $warnings
     */
    private function route(array $step, array &$values, array &$warnings): string
    {
        $id = $step['id'];
        $label = $this->normalizeLabel($this->call($id, $step['agent'], $this->render($step['input'], $values)));

        if (isset($step['routes'][$label])) {
            $target = $step['routes'][$label];
            $values[$id . '.route'] = $label;
        } elseif (isset($step['default'])) {
            $target = $step['default'];
            $values[$id . '.route'] = 'default';
            $warnings[] = sprintf('Стъпка "%s": маршрутът "%s" е непознат, ползван е маршрутът по подразбиране.', $id, mb_substr($label, 0, 40));
        } else {
            // Не се налучква маршрут: по-безопасно е потокът да спре.
            throw new AiException(sprintf('Стъпка "%s": класификаторът върна непознат маршрут "%s".', $id, mb_substr($label, 0, 40)));
        }

        return $this->call($id, $target, $this->render($step['routedInput'], $values));
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $values
     * @param list<string> $warnings
     */
    private function loop(array $step, array $values, array &$warnings): string
    {
        $id = $step['id'];
        $task = $this->render($step['input'], $values);
        $draft = '';
        $feedback = '';

        for ($round = 1; $round <= $step['maxIterations']; $round++) {
            $writerInput = $round === 1
                ? $task
                : $task . "\n\n--- Предишна чернова ---\n" . $draft . "\n\n--- Бележки на рецензента ---\n" . $feedback . "\n\nПодобри черновата според бележките.";
            $draft = $this->call($id, $step['writer'], $writerInput);

            $review = $this->call($id, $step['evaluator'], "Задача:\n" . $task . "\n\nЧернова:\n" . $draft . "\n\n" . self::REVIEW_HINT);
            if (str_starts_with(ltrim($review), self::APPROVED_MARKER)) {
                return $draft;
            }
            $feedback = $review;
        }

        $warnings[] = sprintf('Стъпка "%s": черновата не е одобрена след %d итерации, върната е последната.', $id, $step['maxIterations']);

        return $draft;
    }

    private function call(string $stepId, string $agentCode, string $message): string
    {
        if ($this->calls >= WorkflowDefinition::MAX_AGENT_CALLS) {
            throw new AiException(sprintf('Надвишен е лимитът от %d заявки към агенти.', WorkflowDefinition::MAX_AGENT_CALLS));
        }
        $this->calls++;

        try {
            $result = $this->agents->run($agentCode, $message, [], null, $this->userId);
        } catch (LimitException $e) {
            throw $e;
        } catch (AiException $e) {
            throw new AiException(sprintf('Стъпка "%s": %s', $stepId, $e->getMessage()), 0, $e);
        }

        $this->inputTokens += $result->usage->inputTokens;
        $this->outputTokens += $result->usage->outputTokens;
        $this->cost += $result->cost;

        if ($result->status !== RunStatus::Completed || $result->content === null || trim($result->content) === '') {
            throw new AiException(sprintf('Стъпка "%s": агентът "%s" не завърши (%s).', $stepId, $agentCode, $result->status->value));
        }

        return $result->content;
    }

    /** @param array<string, string> $values */
    private function render(string $template, array $values): string
    {
        // Единичен проход: стойност, съдържаща {{...}}, не се разгръща повторно.
        return (string) preg_replace_callback(
            WorkflowDefinition::PLACEHOLDER,
            static fn (array $m): string => $values[$m[1]] ?? throw new AiException(sprintf('Липсваща стойност за {{%s}}.', $m[1])),
            $template,
        );
    }

    private function normalizeLabel(string $text): string
    {
        $firstLine = preg_split('/\R/u', trim($text), 2)[0] ?? '';

        return mb_strtolower(trim($firstLine, " \t\"'`*.:"));
    }
}
