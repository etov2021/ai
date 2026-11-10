<?php

declare(strict_types=1);

namespace Ai\Limits;

use Ai\Agent\Agent;
use Ai\Agent\RunObserver;
use Ai\Agent\RunResult;
use Ai\Agent\ToolRun;
use Ai\Message\ChatResponse;
use Ai\Pricing\CostCalculator;
use Ai\Storage\UsageRepository;

/** Натрупва токени и разход по обхвати след всяка успешна заявка към модела. */
final class UsageObserver implements RunObserver
{
    /** @param \Closure(): int $clock */
    public function __construct(private UsageRepository $usage, private Agent $agent, private ?int $userId, private \Closure $clock)
    {
    }

    public function llmCall(int $step, ChatResponse $response, int $durationMs): void
    {
        $cost = CostCalculator::cost($response->usage, $this->agent->inputPricePerMillion, $this->agent->outputPricePerMillion);
        $day = date('Y-m-d', ($this->clock)());

        $subjects = ['global' => '', 'provider' => $this->agent->providerCode, 'agent' => (string) $this->agent->id];
        if ($this->userId !== null) {
            $subjects['user'] = (string) $this->userId;
        }
        foreach ($subjects as $scope => $key) {
            $this->usage->record($day, $scope, $key, $response->usage, $cost);
        }
    }

    public function llmFailed(int $step, \Throwable $error, int $durationMs): void
    {
    }

    public function toolRun(int $step, ToolRun $run): void
    {
    }

    public function finished(RunResult $result): void
    {
    }
}
