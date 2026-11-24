<?php

declare(strict_types=1);

namespace Ai\Job;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Exception\AccessDeniedException;
use Ai\Exception\AiException;

/** Задача "изпълни агент": данни {agent, input}. */
final class AgentJobHandler implements JobHandlerInterface
{
    public function __construct(private AgentService $agents)
    {
    }

    public function handle(array $payload, Job $job): string
    {
        $code = (string) ($payload['agent'] ?? '');
        $input = (string) ($payload['input'] ?? '');
        if ($code === '' || trim($input) === '') {
            throw new PermanentJobFailure('Липсва агент или вход.');
        }

        try {
            $result = $this->agents->run($code, $input, userId: $job->userId);
        } catch (AccessDeniedException $e) {
            throw new PermanentJobFailure($e->getMessage(), 0, $e);
        }

        // Спрян, съкратен или блокиран отговор не е успех: повторният опит може да мине.
        if ($result->status !== RunStatus::Completed || $result->content === null || trim($result->content) === '') {
            throw new AiException('Агентът не завърши (' . $result->status->value . ').');
        }

        return trim($result->content);
    }
}
