<?php

declare(strict_types=1);

namespace Ai\Tool;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Contract\ToolInterface;
use Ai\Exception\AiException;
use Ai\Exception\LimitException;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

/** Позволява на един агент да делегира задача на друг (agent-to-agent) през същите лимити, бюджети и логове. */
final class AgentTool implements ToolInterface
{
    public function __construct(
        private AgentService $agents,
        private string $targetAgentCode,
        private string $toolName,
        private string $description,
        private CallDepth $depth,
    ) {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition($this->toolName, $this->description, [
            'type' => 'object',
            'properties' => ['message' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 4000]],
            'required' => ['message'],
        ]);
    }

    public function execute(array $arguments): string
    {
        $this->depth->enter();
        try {
            $result = $this->agents->run($this->targetAgentCode, (string) $arguments['message']);
        } catch (LimitException $e) {
            throw new ToolException($e->getMessage());
        } catch (AiException) {
            // Подробностите на доставчика не се връщат на модела.
            throw new ToolException('Агентът е недостъпен.');
        } finally {
            $this->depth->leave();
        }

        if ($result->status !== RunStatus::Completed || $result->content === null) {
            throw new ToolException('Агентът не завърши задачата.');
        }

        return $result->content;
    }
}
