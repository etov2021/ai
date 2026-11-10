<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\ProviderException;
use Ai\Message\ChatRequest;
use Ai\Message\ChatResponse;
use Ai\Message\FinishReason;
use Ai\Message\Role;
use Ai\Message\ToolCall;
use Ai\Message\Usage;

class OpenAi extends AbstractProvider
{
    public function __construct(
        HttpClientInterface $http,
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl = 'https://api.openai.com/v1',
        int $timeout = 60,
        private readonly string $providerName = 'openai',
        // OpenAI изисква max_completion_tokens (max_tokens е отхвърлен за новите модели); съвместимите сървъри обикновено знаят само max_tokens.
        private readonly string $maxTokensField = 'max_completion_tokens',
    ) {
        parent::__construct($http, $apiKey, $baseUrl, $timeout);
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $headers = $this->apiKey !== '' ? ['Authorization' => 'Bearer ' . $this->apiKey] : [];
        $data = $this->post('/chat/completions', $headers, $this->buildPayload($request));

        return $this->parseResponse($data, $request->model);
    }

    /** @return array<string, mixed> */
    protected function buildPayload(ChatRequest $request): array
    {
        $messages = [];
        if ($request->system !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        foreach ($request->messages as $m) {
            if ($m->role === Role::Tool) {
                $messages[] = ['role' => 'tool', 'tool_call_id' => $m->toolCallId, 'content' => (string) $m->content];
                continue;
            }
            $item = ['role' => $m->role->value, 'content' => $m->content];
            if ($m->toolCalls !== []) {
                $item['tool_calls'] = array_map(static fn (ToolCall $c): array => [
                    'id' => $c->id,
                    'type' => 'function',
                    // "{}" вместо "[]" за празни аргументи
                    'function' => ['name' => $c->name, 'arguments' => json_encode($c->arguments === [] ? new \stdClass() : $c->arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ], $m->toolCalls);
            }
            $messages[] = $item;
        }

        $payload = ['model' => $request->model, 'messages' => $messages];

        if ($request->tools !== []) {
            $payload['tools'] = array_map(static fn ($t): array => [
                'type' => 'function',
                'function' => ['name' => $t->name, 'description' => $t->description, 'parameters' => $t->schema()],
            ], $request->tools);
        }
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $payload[$this->maxTokensField] = $request->maxTokens;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    protected function parseResponse(array $data, string $requestedModel): ChatResponse
    {
        $choice = $data['choices'][0] ?? null;
        if (!is_array($choice) || !isset($choice['message'])) {
            throw new ProviderException($this->name() . ': отговорът не съдържа choices.');
        }

        $toolCalls = [];
        foreach ($choice['message']['tool_calls'] ?? [] as $call) {
            [$args, $error] = $this->parseArguments($call['function']['arguments'] ?? null);
            $toolCalls[] = new ToolCall((string) ($call['id'] ?? ''), (string) ($call['function']['name'] ?? ''), $args, $error);
        }

        $finish = match ($choice['finish_reason'] ?? null) {
            'stop' => FinishReason::Stop,
            'tool_calls', 'function_call' => FinishReason::ToolCalls,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => $toolCalls !== [] ? FinishReason::ToolCalls : FinishReason::Other,
        };

        return new ChatResponse(
            $choice['message']['content'] ?? null,
            $toolCalls,
            $finish,
            new Usage((int) ($data['usage']['prompt_tokens'] ?? 0), (int) ($data['usage']['completion_tokens'] ?? 0)),
            (string) ($data['model'] ?? $requestedModel),
            isset($data['id']) ? (string) $data['id'] : null,
        );
    }

    protected function errorMessage(array $data): ?string
    {
        $message = $data['error']['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    protected function errorCode(array $data): ?string
    {
        $code = $data['error']['code'] ?? $data['error']['type'] ?? null;

        return is_string($code) ? $code : null;
    }
}
