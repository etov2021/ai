<?php

declare(strict_types=1);

namespace Ai\Workflow;

use Ai\Agent\AgentService;
use Ai\Exception\AiException;
use Ai\Extension\ExtensionRegistry;
use Ai\Storage\WorkflowRepository;

/** Намира работен поток по код (първо от базата, после от разширенията) и го изпълнява. */
final class WorkflowService
{
    public function __construct(
        private WorkflowRepository $workflows,
        private AgentService $agents,
        private ExtensionRegistry $extensions,
    ) {
    }

    /** @return array<string, mixed> виж WorkflowResult::toArray() */
    public function run(string $code, string $input, ?int $userId = null): array
    {
        $record = $this->workflows->findByCode($code);

        if ($record === null) {
            return $this->extensions->workflow($code)->run(['input' => $input, 'userId' => $userId]);
        }
        if (!$record->active) {
            throw new AiException('Работният поток не е наличен.');
        }

        return (new WorkflowRunner($this->agents, $record->definition, $userId))->run(['input' => $input]);
    }
}
