<?php

declare(strict_types=1);

namespace Ai\Agent;

use Ai\Exception\AiException;
use Ai\Exception\LimitException;
use Ai\Limits\UsageGuard;
use Ai\Message\Message;
use Ai\Pricing\CostCalculator;
use Ai\Registry\ProviderResolver;
use Ai\Storage\AgentRepository;
use Ai\Storage\RequestLogRepository;
use Ai\Storage\ToolRepository;
use Ai\Tool\ToolRegistry;

/** Входна точка за изпълнение на агент по код: лимити, бюджет, логове и изпълнение. */
final class AgentService
{
    public const ESCALATION_MARKER = '[[ESCALATE]]';

    // Подсказка към евтиния модел: добавя се само когато агентът има модел за ескалация.
    private const ESCALATION_HINT = "\n\nАко въпросът е прекалено сложен или не си сигурен в отговора, не отговаряй по същество, а върни само " . self::ESCALATION_MARKER . '.';

    public function __construct(
        private AgentRepository $agents,
        private ToolRepository $tools,
        private ToolRegistry $registry,
        private ProviderResolver $providers,
        private ?RequestLogRepository $log = null,
        private ?UsageGuard $guard = null,
        // Ограничава цената на една заявка още преди да стигне до модела.
        private int $maxInputChars = 20000,
    ) {
    }

    /**
     * @param list<Message> $history предишни съобщения от разговора, без новото
     * @throws LimitException при надвишен лимит на заявките или бюджет
     */
    public function run(string $agentCode, string $userInput, array $history = [], ?int $conversationId = null, ?int $userId = null): RunResult
    {
        $agent = $this->agents->findByCode($agentCode);
        if ($agent === null || !$agent->runnable) {
            throw new AiException('Агентът не е наличен.');
        }
        if (trim($userInput) === '') {
            throw new AiException('Празно съобщение.');
        }
        if (mb_strlen($userInput) > $this->maxInputChars) {
            throw new AiException(sprintf('Съобщението е прекалено дълго (максимум %d знака).', $this->maxInputChars));
        }

        $logger = $this->log !== null ? new RunLogger($this->log, $agent, $conversationId, $userId) : null;

        if ($this->guard !== null) {
            try {
                foreach ($this->guard->admit($agent, $userId) as $warning) {
                    $logger?->note('warning', $warning);
                }
            } catch (LimitException $e) {
                $logger?->note('rejected', $e->reason);
                throw $e;
            }
        }

        $messages = [...$history, Message::user($userInput)];

        // Ако има модел за ескалация, евтиният отговаря първи и може да поиска по-мощен модел с маркер.
        $first = $this->attempt($agent->escalation !== null ? $agent->withSystemPrompt($agent->systemPrompt . self::ESCALATION_HINT) : $agent, $logger, $messages, $conversationId, $userId);

        if ($agent->escalation === null || !$this->shouldEscalate($first)) {
            return $first;
        }

        // Скъпият модел не трябва да обхожда бюджета, изчерпан от първия опит.
        try {
            $this->guard?->checkBudget($agent, $userId);
        } catch (LimitException $e) {
            $logger?->note('rejected', 'escalation: ' . $e->reason);
            throw $e;
        }
        $logger?->note('escalated', sprintf('%s/%s -> %s/%s', $agent->providerCode, $agent->model, $agent->escalation->providerCode, $agent->escalation->model));

        $escalationLogger = $this->log !== null ? new RunLogger($this->log, $agent->escalation, $conversationId, $userId, $logger?->runId) : null;

        return $this->attempt($agent->escalation, $escalationLogger, $messages, $conversationId, $userId)->withPriorAttempt($first);
    }

    /** Ескалация при изричен маркер, празен отговор или изчерпани стъпки; не се ескалира при блокиране, съкращаване и таймаут. */
    private function shouldEscalate(RunResult $result): bool
    {
        return match ($result->status) {
            RunStatus::MaxSteps => true,
            RunStatus::Completed => $result->content === null || trim($result->content) === '' || str_contains($result->content, self::ESCALATION_MARKER),
            default => false,
        };
    }

    /** @param list<Message> $messages */
    private function attempt(Agent $agent, ?RunLogger $logger, array $messages, ?int $conversationId, ?int $userId): RunResult
    {
        $observers = array_values(array_filter([$logger, $this->guard?->observer($agent, $userId)]));
        $runner = new AgentRunner(
            $this->providers->get($agent->providerCode),
            observer: $observers === [] ? null : new CompositeObserver(...$observers),
            beforeStep: $this->guard?->stepGuard($agent, $userId),
        );

        $result = $runner->run($agent, $this->registry->resolve($this->tools->forAgent($agent->id)), $messages);

        return $result->withCost(CostCalculator::cost($result->usage, $agent->inputPricePerMillion, $agent->outputPricePerMillion));
    }
}
