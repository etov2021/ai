<?php

declare(strict_types=1);

namespace Ai\Job;

use Ai\Exception\AccessDeniedException;
use Ai\Workflow\WorkflowService;

/** Задача "изпълни работен поток": данни {workflow, input}. */
final class WorkflowJobHandler implements JobHandlerInterface
{
    public function __construct(private WorkflowService $workflows)
    {
    }

    public function handle(array $payload, Job $job): string
    {
        $code = (string) ($payload['workflow'] ?? '');
        $input = (string) ($payload['input'] ?? '');
        if ($code === '' || trim($input) === '') {
            throw new PermanentJobFailure('Липсва работен поток или вход.');
        }

        try {
            $result = $this->workflows->run($code, $input, $job->userId);
        } catch (AccessDeniedException $e) {
            throw new PermanentJobFailure($e->getMessage(), 0, $e);
        }

        return trim((string) ($result['output'] ?? ''));
    }
}
