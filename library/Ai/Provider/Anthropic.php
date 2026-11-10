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

final class Anthropic extends AbstractProvider
{
    private const API_VERSION = '2023-06-01';
    // max_tokens е задължително поле в Messages API; стойността е само таван, не се таксува предварително.
    private const DEFAULT_MAX_TOKENS = 4096;

    public function __construct(
        HttpClientInterface $http,
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl = 'https://api.anthropic.com/v1',
        int $timeout = 60,
    ) {
        parent::__construct($http, $apiKey, $baseUrl, $timeout);
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $data = $this->post('/messages', [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ], $this->buildPayload($request));

        return $this->parseResponse($data, $request->model);
    }

    /** @return array<string, mixed> */
    private function buildPayload(ChatRequest $request): array
    {
        $messages = [];
        foreach ($request->messages as $m) {
            if ($m->role === Role::Tool) {
                $block = ['type' => 'tool_result', 'tool_use_id' => $m->toolCallId, 'content' => (string) $m->content];
                // Всички резултати след един ход на асистента трябва да са в ЕДНО user съобщение.
                $last = count($messages) - 1;
                if ($last >= 0 && $messages[$last]['role'] === 'user' && is_array($messages[$last]['content']) && ($messages[$last]['content'][0]['type'] ?? null) === 'tool_result') {
                    $messages[$last]['content'][] = $block;
                } else {
                    $messages[] = ['role' => 'user', 'content' => [$block]];
                }
                continue;
            }

            if ($m->role === Role::Assistant) {
                $blocks = [];
                if ($m->content !== null && $m->content !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $m->content];
                }
                foreach ($m->toolCalls as $c) {
                    $blocks[] = ['type' => 'tool_use', 'id' => $c->id, 'name' => $c->name, 'input' => $c->arguments === [] ? new \stdClass() : $c->arguments];
                }
                $messages[] = ['role' => 'assistant', 'content' => $blocks];
                continue;
            }

            $messages[] = ['role' => 'user', 'content' => (string) $m->content];
        }

        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $messages,
        ];

        if ($request->system !== null) {
            $payload['system'] = $request->system;
        }
        if ($request->tools !== []) {
            $payload['tools'] = array_map(static fn ($t): array => [
                'name' => $t->name,
                'description' => $t->description,
                'input_schema' => $t->schema(),
            ], $request->tools);
        }
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function parseResponse(array $data, string $requestedModel): ChatResponse
    {
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new ProviderException('anthropic: отговорът не съдържа content.');
        }

        $text = '';
        $toolCalls = [];
        foreach ($data['content'] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) $block['text'];
            } elseif (($block['type'] ?? null) === 'tool_use') {
                [$args, $error] = $this->parseArguments($block['input'] ?? []);
                $toolCalls[] = new ToolCall((string) $block['id'], (string) $block['name'], $args, $error);
            }
        }

        $finish = match ($data['stop_reason'] ?? null) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'tool_use' => FinishReason::ToolCalls,
            'max_tokens' => FinishReason::Length,
            'refusal' => FinishReason::ContentFilter,
            default => FinishReason::Other,
        };

        return new ChatResponse(
            $text === '' ? null : $text,
            $toolCalls,
            $finish,
            new Usage((int) ($data['usage']['input_tokens'] ?? 0), (int) ($data['usage']['output_tokens'] ?? 0)),
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
        $type = $data['error']['type'] ?? null;

        return is_string($type) ? $type : null;
    }
}
