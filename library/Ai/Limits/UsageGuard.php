<?php

declare(strict_types=1);

namespace Ai\Limits;

use Ai\Agent\Agent;
use Ai\Agent\RunObserver;
use Ai\Exception\BudgetExceededException;
use Ai\Exception\RateLimitException;
use Ai\Storage\BudgetRepository;
use Ai\Storage\UsageRepository;

/** Проверява лимити на заявките и бюджети преди изпълнение и преди всяка следваща стъпка. */
final class UsageGuard
{
    /** @var \Closure(): int */
    private \Closure $clock;

    /** @param ?\Closure(): int $clock Unix време; подменя се в тестове */
    public function __construct(
        private BudgetRepository $budgets,
        private UsageRepository $usage,
        private RateLimiter $limiter,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Брои заявката към лимитите и проверява бюджетите.
     *
     * @return list<string> предупреждения за достигнат праг (напр. 80% от дневния бюджет)
     * @throws RateLimitException
     * @throws BudgetExceededException
     */
    public function admit(Agent $agent, ?int $userId): array
    {
        $rules = $this->budgets->applicable($agent, $userId);
        $now = ($this->clock)();

        foreach ($rules as $rule) {
            if ($rule->rateLimitPerMinute === null) {
                continue;
            }
            $retry = $this->limiter->hit('rl:' . $rule->label(), $rule->rateLimitPerMinute, $now);
            if ($retry !== null) {
                throw new RateLimitException(max(1, $retry), sprintf('%s: надвишен лимит от %d заявки/мин', $rule->label(), $rule->rateLimitPerMinute));
            }
        }

        return $this->checkBudgets($rules);
    }

    /** Само проверка на бюджетите, без броене на заявка към лимита (за ескалация след първия опит).
     *
     * @throws BudgetExceededException
     */
    public function checkBudget(Agent $agent, ?int $userId): void
    {
        $this->checkBudgets($this->budgets->applicable($agent, $userId));
    }

    /** Стъпка 1 е проверена от admit(); следващите стъпки спират, ако бюджетът е изчерпан междувременно. */
    public function stepGuard(Agent $agent, ?int $userId): \Closure
    {
        return function (int $step) use ($agent, $userId): void {
            if ($step > 1) {
                $this->checkBudgets($this->budgets->applicable($agent, $userId));
            }
        };
    }

    public function observer(Agent $agent, ?int $userId): RunObserver
    {
        return new UsageObserver($this->usage, $agent, $userId, $this->clock);
    }

    /**
     * @param list<AppliedRule> $rules
     * @return list<string>
     */
    private function checkBudgets(array $rules): array
    {
        $now = ($this->clock)();
        $today = date('Y-m-d', $now);
        $monthStart = date('Y-m-01', $now);
        $warnings = [];

        foreach ($rules as $rule) {
            foreach ([['дневен', $rule->dailyLimit, $today], ['месечен', $rule->monthlyLimit, $monthStart]] as [$name, $limit, $from]) {
                if ($limit === null) {
                    continue;
                }
                $spent = $this->usage->spent($rule->scope, $rule->key, $from, $today);
                if ($spent >= $limit) {
                    throw new BudgetExceededException(sprintf('%s: %s бюджет %.4f EUR, изразходвани %.4f EUR', $rule->label(), $name, $limit, $spent));
                }
                if ($limit > 0 && $spent >= $limit * $rule->warnPercent / 100) {
                    $warnings[] = sprintf('%s: достигнати %d%% от %s бюджет (%.4f от %.4f EUR)', $rule->label(), $rule->warnPercent, $name, $spent, $limit);
                }
            }
        }

        return $warnings;
    }
}
