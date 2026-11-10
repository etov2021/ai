<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\ProviderException;
use Ai\Message\ChatRequest;
use Ai\Message\ChatResponse;
use Ai\Message\FinishReason;
use Ai\Message\Role;
use Ai\Message\ToolCall;
use Ai\Message\Usage;

final class Gemini extends AbstractProvider
{
    public function __construct(
        HttpClientInterface $http,
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        int $timeout = 60,
    ) {
        parent::__construct($http, $apiKey, $baseUrl, $timeout);
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        // Моделът отива в пътя на URL-а, затова се ограничава до безопасни знаци.
        if (preg_match('/^[A-Za-z0-9._-]+$/', $request->model) !== 1) {
            throw new AiException('Невалидно име на модел за Gemini.');
        }

        // Ключът е в заглавие, не в ?key=, за да не попада в URL-и и логове на прокси сървъри.
        $data = $this->post('/models/' . $request->model . ':generateContent', ['x-goog-api-key' => $this->apiKey], $this->buildPayload($request));

        return $this->parseResponse($data, $request->model);
    }

    /** @return array<string, mixed> */
    private function buildPayload(ChatRequest $request): array
    {
        $contents = [];
        foreach ($request->messages as $m) {
            if ($m->role === Role::Tool) {
                $part = ['functionResponse' => ['name' => (string) $m->name, 'response' => $this->toResponseObject((string) $m->content)]];
                $last = count($contents) - 1;
                if ($last >= 0 && $contents[$last]['role'] === 'user' && isset($contents[$last]['parts'][0]['functionResponse'])) {
                    $contents[$last]['parts'][] = $part;
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [$part]];
                }
                continue;
            }

            if ($m->role === Role::Assistant) {
                $parts = [];
                if ($m->content !== null && $m->content !== '') {
                    $parts[] = ['text' => $m->content];
                }
                foreach ($m->toolCalls as $c) {
                    $part = ['functionCall' => ['name' => $c->name, 'args' => $c->arguments === [] ? new \stdClass() : $c->arguments]];
                    if ($c->opaque !== null) {
                        $part['thoughtSignature'] = $c->opaque;
                    }
                    $parts[] = $part;
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            $contents[] = ['role' => 'user', 'parts' => [['text' => (string) $m->content]]];
        }

        $payload = ['contents' => $contents];

        if ($request->system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $request->system]]];
        }
        if ($request->tools !== []) {
            $declarations = [];
            foreach ($request->tools as $t) {
                $declaration = ['name' => $t->name, 'description' => $t->description];
                $schema = $this->sanitizeSchema($t->schema());
                // Gemini отхвърля параметри с празен списък от свойства.
                if (!empty((array) ($schema['properties'] ?? []))) {
                    $declaration['parameters'] = $schema;
                }
                $declarations[] = $declaration;
            }
            $payload['tools'] = [['functionDeclarations' => $declarations]];
        }

        $config = [];
        if ($request->temperature !== null) {
            $config['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $config['maxOutputTokens'] = $request->maxTokens;
        }
        if ($config !== []) {
            $payload['generationConfig'] = $config;
        }

        return $payload;
    }

    /** @return array<string, mixed> functionResponse.response трябва да е JSON обект */
    private function toResponseObject(string $content): array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded) && $decoded !== [] && !array_is_list($decoded)) {
            return $decoded;
        }

        return ['result' => $content];
    }

    /**
     * Gemini приема подмножество на OpenAPI схема.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function sanitizeSchema(array $schema): array
    {
        unset($schema['$schema'], $schema['additionalProperties'], $schema['$ref'], $schema['$defs'], $schema['definitions']);

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->sanitizeSchema($value);
            }
        }

        return $schema;
    }

    /** @param array<string, mixed> $data */
    private function parseResponse(array $data, string $requestedModel): ChatResponse
    {
        $usage = new Usage(
            (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
            // мисленето (thoughts) се таксува като изходни токени
            (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0) + (int) ($data['usageMetadata']['thoughtsTokenCount'] ?? 0),
        );
        $model = (string) ($data['modelVersion'] ?? $requestedModel);
        $id = isset($data['responseId']) ? (string) $data['responseId'] : null;

        $candidate = $data['candidates'][0] ?? null;
        if (!is_array($candidate)) {
            if (isset($data['promptFeedback']['blockReason'])) {
                return new ChatResponse(null, [], FinishReason::ContentFilter, $usage, $model, $id);
            }
            throw new ProviderException('gemini: отговорът не съдържа candidates.');
        }

        $text = '';
        $toolCalls = [];
        foreach ($candidate['content']['parts'] ?? [] as $i => $part) {
            if (isset($part['functionCall'])) {
                [$args, $error] = $this->parseArguments($part['functionCall']['args'] ?? []);
                $toolCalls[] = new ToolCall(
                    (string) ($part['functionCall']['id'] ?? 'call_' . $i),
                    (string) $part['functionCall']['name'],
                    $args,
                    $error,
                    isset($part['thoughtSignature']) ? (string) $part['thoughtSignature'] : null,
                );
            } elseif (isset($part['text']) && empty($part['thought'])) {
                $text .= (string) $part['text'];
            }
        }

        $finish = match ($candidate['finishReason'] ?? null) {
            'STOP' => $toolCalls !== [] ? FinishReason::ToolCalls : FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII' => FinishReason::ContentFilter,
            default => $toolCalls !== [] ? FinishReason::ToolCalls : FinishReason::Other,
        };

        return new ChatResponse($text === '' ? null : $text, $toolCalls, $finish, $usage, $model, $id);
    }

    protected function errorMessage(array $data): ?string
    {
        $message = $data['error']['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    protected function errorCode(array $data): ?string
    {
        $status = $data['error']['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
