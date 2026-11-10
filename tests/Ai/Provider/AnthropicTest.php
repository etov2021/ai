<?php

declare(strict_types=1);

namespace Ai\Tests\Provider;

use Ai\Message\ChatRequest;
use Ai\Message\FinishReason;
use Ai\Message\Message;
use Ai\Message\ToolCall;
use Ai\Message\ToolDefinition;
use Ai\Provider\Anthropic;
use Ai\Tests\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class AnthropicTest extends TestCase
{
    public function testBuildsRequestAndParsesText(): void
    {
        $http = new FakeHttpClient([
            'id' => 'msg_1', 'model' => 'claude-x', 'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Здравей']],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
        ]);

        $response = (new Anthropic($http, 'ak'))->chat(new ChatRequest('claude-x', [Message::system('S2'), Message::user('Hi')], 'S1'));

        $this->assertSame('https://api.anthropic.com/v1/messages', $http->url);
        $this->assertSame('ak', $http->headers['x-api-key']);
        $this->assertSame('2023-06-01', $http->headers['anthropic-version']);
        $this->assertSame("S1\n\nS2", $http->payload['system']);
        $this->assertSame(4096, $http->payload['max_tokens']);
        $this->assertSame([['role' => 'user', 'content' => 'Hi']], $http->payload['messages']);
        $this->assertSame('Здравей', $response->content);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(15, $response->usage->totalTokens());
    }

    public function testToolUseAndMergedToolResults(): void
    {
        $http = new FakeHttpClient([
            'stop_reason' => 'tool_use',
            'content' => [
                ['type' => 'text', 'text' => 'Проверявам'],
                ['type' => 'tool_use', 'id' => 'tu1', 'name' => 'weather', 'input' => ['city' => 'Русе']],
            ],
        ]);
        $tool = new ToolDefinition('weather', 'Времето', ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]);

        $response = (new Anthropic($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')], tools: [$tool]));

        $this->assertSame('weather', $http->payload['tools'][0]['name']);
        $this->assertArrayHasKey('input_schema', $http->payload['tools'][0]);
        $this->assertSame(FinishReason::ToolCalls, $response->finishReason);
        $this->assertSame(['city' => 'Русе'], $response->toolCalls[0]->arguments);

        $http2 = new FakeHttpClient(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'ok']]]);
        (new Anthropic($http2, 'k'))->chat(new ChatRequest('m', [
            Message::user('?'),
            Message::assistant('x', [new ToolCall('a', 'f1'), new ToolCall('b', 'f2', ['q' => 1])]),
            Message::toolResult('a', 'f1', 'r1'),
            Message::toolResult('b', 'f2', 'r2'),
        ]));

        $messages = $http2->payload['messages'];
        $this->assertCount(3, $messages);
        $this->assertSame('tool_use', $messages[1]['content'][1]['type']);
        $this->assertSame([], $messages[1]['content'][1]['input']);
        $this->assertSame('user', $messages[2]['role']);
        $this->assertCount(2, $messages[2]['content']);
        $this->assertSame('b', $messages[2]['content'][1]['tool_use_id']);
    }

    public function testMaxTokensFinishReason(): void
    {
        $http = new FakeHttpClient(['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => 'abc']]]);

        $response = (new Anthropic($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')], maxTokens: 5));

        $this->assertSame(5, $http->payload['max_tokens']);
        $this->assertSame(FinishReason::Length, $response->finishReason);
    }

    public function testErrorBody(): void
    {
        $http = new FakeHttpClient(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529);

        try {
            (new Anthropic($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')]));
            $this->fail('Очаква се изключение.');
        } catch (\Ai\Exception\ProviderException $e) {
            $this->assertSame('overloaded_error', $e->providerCode);
            $this->assertTrue($e->isRetryable());
        }
    }
}
