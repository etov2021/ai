<?php

declare(strict_types=1);

namespace Ai\Tests\Provider;

use Ai\Exception\AiException;
use Ai\Message\ChatRequest;
use Ai\Message\FinishReason;
use Ai\Message\Message;
use Ai\Message\ToolCall;
use Ai\Message\ToolDefinition;
use Ai\Provider\Gemini;
use Ai\Tests\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class GeminiTest extends TestCase
{
    public function testBuildsRequestAndParsesText(): void
    {
        $http = new FakeHttpClient([
            'modelVersion' => 'gemini-x', 'responseId' => 'r1',
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
                ['text' => 'размисъл', 'thought' => true],
                ['text' => 'Здравей'],
            ]]]],
            'usageMetadata' => ['promptTokenCount' => 8, 'candidatesTokenCount' => 2, 'thoughtsTokenCount' => 5],
        ]);

        $response = (new Gemini($http, 'gk'))->chat(new ChatRequest('gemini-x', [Message::user('Hi')], 'Сист', temperature: 0.5, maxTokens: 64));

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-x:generateContent', $http->url);
        $this->assertSame('gk', $http->headers['x-goog-api-key']);
        $this->assertStringNotContainsString('gk', $http->url);
        $this->assertSame('Сист', $http->payload['systemInstruction']['parts'][0]['text']);
        $this->assertSame(['temperature' => 0.5, 'maxOutputTokens' => 64], $http->payload['generationConfig']);
        $this->assertSame('Здравей', $response->content);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(7, $response->usage->outputTokens);
    }

    public function testFunctionCallRoundTripKeepsThoughtSignature(): void
    {
        $http = new FakeHttpClient(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['functionCall' => ['name' => 'weather', 'args' => ['city' => 'Русе']], 'thoughtSignature' => 'sig123'],
        ]]]]]);
        $tool = new ToolDefinition('weather', 'Времето', [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => ['city' => ['type' => 'string']],
        ]);

        $response = (new Gemini($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')], tools: [$tool]));

        $declaration = $http->payload['tools'][0]['functionDeclarations'][0];
        $this->assertArrayNotHasKey('additionalProperties', $declaration['parameters']);
        $this->assertSame(FinishReason::ToolCalls, $response->finishReason);
        $this->assertSame('sig123', $response->toolCalls[0]->opaque);

        $http2 = new FakeHttpClient(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'ok']]]]]]);
        (new Gemini($http2, 'k'))->chat(new ChatRequest('m', [
            Message::user('?'),
            $response->toMessage(),
            Message::toolResult('call_0', 'weather', '{"t":20}'),
            Message::toolResult('call_1', 'other', 'plain text'),
        ]));

        $contents = $http2->payload['contents'];
        $this->assertCount(3, $contents);
        $this->assertSame('model', $contents[1]['role']);
        $this->assertSame('sig123', $contents[1]['parts'][0]['thoughtSignature']);
        $this->assertSame(['t' => 20], $contents[2]['parts'][0]['functionResponse']['response']);
        $this->assertSame(['result' => 'plain text'], $contents[2]['parts'][1]['functionResponse']['response']);
    }

    public function testToolWithoutParametersOmitsSchema(): void
    {
        $http = new FakeHttpClient(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'ok']]]]]]);

        (new Gemini($http, 'k'))->chat(new ChatRequest('m', [Message::user('?'), Message::assistant(null, [new ToolCall('c', 'ping')])], tools: [new ToolDefinition('ping', 'Пинг')]));

        $this->assertArrayNotHasKey('parameters', $http->payload['tools'][0]['functionDeclarations'][0]);
        $this->assertEquals(new \stdClass(), (object) $http->payload['contents'][1]['parts'][0]['functionCall']['args']);
    }

    public function testBlockedPromptReturnsContentFilter(): void
    {
        $http = new FakeHttpClient(['promptFeedback' => ['blockReason' => 'SAFETY']]);

        $response = (new Gemini($http, 'k'))->chat(new ChatRequest('m', [Message::user('?')]));

        $this->assertSame(FinishReason::ContentFilter, $response->finishReason);
        $this->assertNull($response->content);
    }

    public function testModelNameIsValidatedBeforeBuildingUrl(): void
    {
        $this->expectException(AiException::class);

        (new Gemini(new FakeHttpClient([]), 'k'))->chat(new ChatRequest('../evil?x=1', [Message::user('?')]));
    }
}
