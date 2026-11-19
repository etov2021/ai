<?php

declare(strict_types=1);

namespace Ai\McpServer;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Contract\ToolInterface;
use Ai\Exception\AiException;
use Ai\Exception\LimitException;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

/** Позволява на външен клиент да зададе въпрос на един фиксиран агент (избран при създаване на токена, не от клиента). */
final class AskAgentTool implements ToolInterface
{
    public const NAME = 'ask_agent';

    public function __construct(private AgentService $agents, private string $agentCode)
    {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(self::NAME, 'Задава въпрос на AI агент и връща отговора му на български.', [
            'type' => 'object',
            'properties' => ['question' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 4000, 'description' => 'въпросът към агента']],
            'required' => ['question'],
        ]);
    }

    public function execute(array $arguments): string
    {
        try {
            $result = $this->agents->run($this->agentCode, (string) $arguments['question']);
        } catch (LimitException $e) {
            throw new ToolException($e->getMessage());
        } catch (AiException) {
            // Подробностите на доставчика не излизат навън.
            throw new ToolException('Агентът е недостъпен.');
        }

        if ($result->status !== RunStatus::Completed || $result->content === null) {
            throw new ToolException('Агентът не завърши задачата.');
        }

        return $result->content;
    }
}
