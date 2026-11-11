<?php

declare(strict_types=1);

namespace Ai\Workflow;

use Ai\Exception\AiException;

/**
 * Валидирано описание на работен поток: последователност от стъпки agent / router / loop.
 * Всичко се проверява при запис, така че изпълнението да не среща непредвидена структура.
 */
final class WorkflowDefinition
{
    public const MAX_STEPS = 10;
    public const MAX_ITERATIONS = 5;
    // Горна граница на заявките към агенти в едно изпълнение: пази бюджета от цикли и дълги вериги.
    public const MAX_AGENT_CALLS = 30;
    public const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]*(?:\.route)?)\s*\}\}/';

    private const STEP_ID = '/^[a-z][a-z0-9_]{0,29}$/';
    private const AGENT_CODE = '/^[a-z][a-z0-9_-]{1,39}$/';
    private const ROUTE_LABEL = '/^[a-z0-9_-]{1,40}$/';
    private const MAX_TEMPLATE = 4000;
    private const MAX_ROUTES = 10;

    /** @param list<array<string, mixed>> $steps */
    private function __construct(public readonly array $steps)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $steps = $data['steps'] ?? null;
        if (!is_array($steps) || !array_is_list($steps) || $steps === []) {
            throw new AiException('Описанието трябва да съдържа непразен списък "steps".');
        }
        if (count($steps) > self::MAX_STEPS) {
            throw new AiException(sprintf('Максимум %d стъпки.', self::MAX_STEPS));
        }

        // id на стъпка => дали има .route (само router)
        $known = [];
        $calls = 0;
        $normalized = [];

        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                throw new AiException(sprintf('Стъпка %d не е обект.', $i + 1));
            }
            $id = $step['id'] ?? null;
            if (!is_string($id) || preg_match(self::STEP_ID, $id) !== 1 || $id === 'input') {
                throw new AiException(sprintf('Стъпка %d: "id" е малки латински букви, цифри и _, започва с буква (не "input").', $i + 1));
            }
            if (isset($known[$id])) {
                throw new AiException(sprintf('Повтарящ се id "%s".', $id));
            }

            $type = $step['type'] ?? null;
            $normalized[] = match ($type) {
                'agent' => self::agentStep($id, $step, $known, $calls),
                'router' => self::routerStep($id, $step, $known, $calls),
                'loop' => self::loopStep($id, $step, $known, $calls),
                default => throw new AiException(sprintf('Стъпка "%s": непознат тип (agent, router или loop).', $id)),
            };
            $known[$id] = $type === 'router';
        }

        if ($calls > self::MAX_AGENT_CALLS) {
            throw new AiException(sprintf('В най-лошия случай потокът прави %d заявки към агенти; допустими са %d.', $calls, self::MAX_AGENT_CALLS));
        }

        return new self($normalized);
    }

    /** @return array{steps: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['steps' => $this->steps];
    }

    /** @return list<string> */
    public function agentCodes(): array
    {
        $codes = [];
        foreach ($this->steps as $step) {
            $codes = [...$codes, ...match ($step['type']) {
                'agent' => [$step['agent']],
                'router' => [$step['agent'], ...array_values($step['routes']), ...(isset($step['default']) ? [$step['default']] : [])],
                'loop' => [$step['writer'], $step['evaluator']],
            }];
        }

        return array_values(array_unique($codes));
    }

    /** @param array<string, bool> $known */
    private static function agentStep(string $id, array $step, array $known, int &$calls): array
    {
        $calls += 1;

        return [
            'id' => $id,
            'type' => 'agent',
            'agent' => self::agentCode($id, $step['agent'] ?? null),
            'input' => self::template($id, $step['input'] ?? null, $known),
        ];
    }

    /** @param array<string, bool> $known */
    private static function routerStep(string $id, array $step, array $known, int &$calls): array
    {
        $routes = $step['routes'] ?? null;
        if (!is_array($routes) || $routes === [] || count($routes) > self::MAX_ROUTES) {
            throw new AiException(sprintf('Стъпка "%s": "routes" е обект с 1-%d маршрута (етикет => код на агент).', $id, self::MAX_ROUTES));
        }
        $cleanRoutes = [];
        foreach ($routes as $label => $agent) {
            if (preg_match(self::ROUTE_LABEL, (string) $label) !== 1) {
                throw new AiException(sprintf('Стъпка "%s": етикетът на маршрут е малки латински букви, цифри, _ и -.', $id));
            }
            $cleanRoutes[(string) $label] = self::agentCode($id, $agent);
        }

        $input = self::template($id, $step['input'] ?? null, $known);
        $calls += 2;

        $result = [
            'id' => $id,
            'type' => 'router',
            'agent' => self::agentCode($id, $step['agent'] ?? null),
            'input' => $input,
            'routes' => $cleanRoutes,
            'routedInput' => isset($step['routedInput']) ? self::template($id, $step['routedInput'], $known) : $input,
        ];
        if (isset($step['default'])) {
            $result['default'] = self::agentCode($id, $step['default']);
        }

        return $result;
    }

    /** @param array<string, bool> $known */
    private static function loopStep(string $id, array $step, array $known, int &$calls): array
    {
        $max = $step['maxIterations'] ?? 3;
        if (!is_int($max) || $max < 1 || $max > self::MAX_ITERATIONS) {
            throw new AiException(sprintf('Стъпка "%s": "maxIterations" е цяло число от 1 до %d.', $id, self::MAX_ITERATIONS));
        }
        $calls += 2 * $max;

        return [
            'id' => $id,
            'type' => 'loop',
            'writer' => self::agentCode($id, $step['writer'] ?? null),
            'evaluator' => self::agentCode($id, $step['evaluator'] ?? null),
            'input' => self::template($id, $step['input'] ?? null, $known),
            'maxIterations' => $max,
        ];
    }

    private static function agentCode(string $id, mixed $code): string
    {
        if (!is_string($code) || preg_match(self::AGENT_CODE, $code) !== 1) {
            throw new AiException(sprintf('Стъпка "%s": невалиден код на агент.', $id));
        }

        return $code;
    }

    /** @param array<string, bool> $known */
    private static function template(string $id, mixed $template, array $known): string
    {
        if (!is_string($template) || trim($template) === '' || mb_strlen($template) > self::MAX_TEMPLATE) {
            throw new AiException(sprintf('Стъпка "%s": "input" е текст до %d знака.', $id, self::MAX_TEMPLATE));
        }

        preg_match_all(self::PLACEHOLDER, $template, $matches);
        foreach ($matches[1] as $ref) {
            [$name, $suffix] = array_pad(explode('.', $ref, 2), 2, null);
            $valid = $ref === 'input' || (isset($known[$name]) && ($suffix === null || $known[$name]));
            if (!$valid) {
                throw new AiException(sprintf('Стъпка "%s": непозната препратка {{%s}} (позволени са "input", предходни стъпки и {{router.route}}).', $id, $ref));
            }
        }

        return $template;
    }
}
