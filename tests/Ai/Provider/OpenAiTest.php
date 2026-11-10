<?php

declare(strict_types=1);

namespace Ai\Tests\Provider;

use Ai\Exception\AiException;
use Ai\Exception\ProviderException;
use Ai\Message\ChatRequest;
use Ai\Message\FinishReason;
use Ai\Message\Message;
use Ai\Message\ToolCall;
use Ai\Message\ToolDefinition;
use Ai\Provider\OpenAi;
use Ai\Provider\OpenAiCompatible;
use Ai\Tests\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class OpenAiTest extends TestCase
{
    public function testBuildsRequestAndParsesText(): void
    {
        $http = new FakeHttpClient([
            'id' => 'chatcmpl-1', 'model' => 'gpt-x',
            'choices' => [['message' => ['content' => 'Здравей'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
        ]);

        $response = (new OpenAi($http, 'sk-test'))->chat(new ChatRequest('gpt-x', [Message::user('Hi')], 'Бъди кратък', maxTokens: 100, temperature: 0.2));

        $this->assertSame('https://api.openai.com/v1/chat/completions', $http->url);
        $this->assertSame('Bearer sk-test', $http->headers['Authorization']);
        $this->assertSame('system', $http->payload['messages'][0]['role']);
        $this->assertSame(100, $http->payload['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $http->payload);
        $this->assertSame('Здравей', $response->content);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(14, $response->usage->totalTokens());
    }

    public function testToolCallRoundTrip(): void
    {
        $http = new FakeHttpClient([
            'choices' => [['message' => ['content' => null, 'tool_calls' => [
                ['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Русе"}']],
            ]], 'finish_reason' => 'tool_calls']],
        ]);
        $tool = new ToolDefinition('weather', 'Времето', ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]);

        $response = (new OpenAi($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')], tools: [$tool]));

        $this->assertSame('function', $http->payload['tools'][0]['type']);
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame(['city' => 'Русе'], $response->toolCalls[0]->arguments);
        $this->assertSame(FinishReason::ToolCalls, $response->finishReason);

        $http2 = new FakeHttpClient(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']]]);
        (new OpenAi($http2, 'k'))->chat(new ChatRequest('m', [
            Message::user('?'),
            $response->toMessage(),
            Message::toolResult('c1', 'weather', '{"t":20}'),
        ]));

        $assistant = $http2->payload['messages'][1];
        $this->assertSame('{"city":"Русе"}', $assistant['tool_calls'][0]['function']['arguments']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'c1', 'content' => '{"t":20}'], $http2->payload['messages'][2]);
    }

    public function testInvalidToolArgumentsAreFlagged(): void
    {
        $http = new FakeHttpClient(['choices' => [['message' => ['tool_calls' => [
            ['id' => 'c1', 'function' => ['name' => 'x', 'arguments' => '{bad']],
        ]], 'finish_reason' => 'tool_calls']]]);

        $call = (new OpenAi($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')]))->toolCalls[0];

        $this->assertNotNull($call->parseError);
    }

    public function testEmptyToolArgumentsEncodeAsObject(): void
    {
        $http = new FakeHttpClient(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']]]);
        (new OpenAi($http, 'k'))->chat(new ChatRequest('m', [Message::user('?'), Message::assistant(null, [new ToolCall('c', 'ping')])]));

        $this->assertSame('{}', $http->payload['messages'][1]['tool_calls'][0]['function']['arguments']);
    }

    public function testHttpErrorBecomesProviderException(): void
    {
        $http = new FakeHttpClient(['error' => ['message' => 'Rate limit', 'code' => 'rate_limit_exceeded']], 429);

        try {
            (new OpenAi($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')]));
            $this->fail('Очаква се изключение.');
        } catch (ProviderException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('rate_limit_exceeded', $e->providerCode);
            $this->assertTrue($e->isRetryable());
            $this->assertStringContainsString('Rate limit', $e->getMessage());
        }
    }

    public function testApiKeyIsRequiredAndNotDumped(): void
    {
        try {
            new OpenAi(new FakeHttpClient([]), '');
            $this->fail('Очаква се изключение.');
        } catch (AiException) {
            $this->addToAssertionCount(1);
        }

        $provider = new OpenAi(new FakeHttpClient([]), 'sk-secret-value');
        $this->assertStringNotContainsString('sk-secret-value', print_r($provider, true));
    }

    public function testCompatibleProviderUsesMaxTokensAndWorksWithoutKey(): void
    {
        $http = new FakeHttpClient(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']]]);
        $provider = new OpenAiCompatible($http, 'http://localhost:11434/v1/', 'ollama');

        $provider->chat(new ChatRequest('llama3', [Message::user('?')], maxTokens: 50));

        $this->assertSame('ollama', $provider->name());
        $this->assertSame('http://localhost:11434/v1/chat/completions', $http->url);
        $this->assertSame(50, $http->payload['max_tokens']);
        $this->assertArrayNotHasKey('Authorization', $http->headers);
    }
}
