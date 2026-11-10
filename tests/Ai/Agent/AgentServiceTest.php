<?php

declare(strict_types=1);

namespace Ai\Tests\Agent;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Exception\AiException;
use Ai\Http\CurlClient;
use Ai\Registry\ProviderRegistry;
use Ai\Registry\ProviderResolver;
use Ai\Security\KeyVault;
use Ai\Security\Policy;
use Ai\Storage\AgentRepository;
use Ai\Storage\ModelRepository;
use Ai\Storage\ProviderRepository;
use Ai\Storage\ToolRepository;
use Ai\Tests\FakeHttpClient;
use Ai\Tests\SqliteDatabase;
use Ai\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class AgentServiceTest extends TestCase
{
    private SqliteDatabase $db;
    private Policy $policy;
    private ProviderRepository $providers;
    private ToolRepository $tools;
    private AgentRepository $agents;
    private int $modelId;

    protected function setUp(): void
    {
        $this->db = new SqliteDatabase();
        $this->policy = new Policy(false, static fn (string $h): array => ['93.184.216.34']);
        $registry = new ProviderRegistry(new CurlClient());
        $this->providers = new ProviderRepository($this->db, new KeyVault(str_repeat('m', 32)), $this->policy, $registry);
        $this->tools = new ToolRepository($this->db, $this->policy);
        $this->agents = new AgentRepository($this->db, $this->policy);

        $provider = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');
        $this->modelId = (new ModelRepository($this->db, $this->policy))->save($provider->id, 'gpt-x', 'GPT X', 1, 2)->id;
    }

    private function service(FakeHttpClient $llmAndTools): AgentService
    {
        $registry = new ProviderRegistry($llmAndTools);

        return new AgentService(
            $this->agents,
            $this->tools,
            new ToolRegistry($llmAndTools, $this->policy),
            new ProviderResolver($this->providers, $registry),
        );
    }

    private function saveWeatherTool(bool $active = true): void
    {
        $this->tools->save('weather', 'Времето', 'Връща времето за град', 'http', [
            'type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city'],
        ], ['method' => 'GET', 'url' => 'https://api.example.com/weather/{city}', 'allowedHosts' => ['api.example.com']], $active);
    }

    public function testAgentCrudStoresToolAssignments(): void
    {
        $this->saveWeatherTool();
        $agent = $this->agents->save('helper', 'Помощник', 'Отговаряй кратко', $this->modelId, ['weather'], 0.2, 300, 4, 90);

        $this->assertSame('gpt-x', $agent->model);
        $this->assertSame('openai', $agent->providerCode);
        $this->assertSame(['weather'], $agent->toolCodes);
        $this->assertTrue($agent->runnable);

        $this->assertSame([], $this->agents->save('helper', 'Помощник', 'Отговаряй кратко', $this->modelId)->toolCodes);
    }

    public function testAgentValidation(): void
    {
        foreach ([
            fn () => $this->agents->save('Bad Code', 'N', 'p', $this->modelId),
            fn () => $this->agents->save('ok', 'N', '', $this->modelId),
            fn () => $this->agents->save('ok', 'N', 'p', 999),
            fn () => $this->agents->save('ok', 'N', 'p', $this->modelId, ['missing']),
            fn () => $this->agents->save('ok', 'N', 'p', $this->modelId, maxSteps: 99),
            fn () => $this->agents->save('ok', 'N', 'p', $this->modelId, temperature: 5.0),
            fn () => $this->tools->save('t', 'T', 'd', 'http', ['type' => 'object'], ['method' => 'GET', 'url' => 'https://x.example/', 'allowedHosts' => ['y.example']], true),
            fn () => $this->tools->save('t', 'T', 'd', 'nope', ['type' => 'object'], [], true),
            fn () => $this->tools->save('t', 'T', 'd', 'builtin', ['type' => 'string'], [], true),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testEndToEndRunWithHttpToolAndLlmOverHttp(): void
    {
        $this->saveWeatherTool();
        $this->agents->save('helper', 'Помощник', 'Отговаряй кратко', $this->modelId, ['weather']);

        // Един клиент играе и LLM, и външното API: първо tool call, после финален отговор.
        $http = new class extends FakeHttpClient {
            public array $sent = [];
            private int $n = 0;

            public function __construct()
            {
            }

            public function post(string $url, array $headers, string $body, int $timeout): \Ai\Http\HttpResponse
            {
                $this->sent[] = json_decode($body, true);
                $responses = [
                    ['choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Русе"}']]]]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4]],
                    ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '20 градуса']]], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 3]],
                ];

                return new \Ai\Http\HttpResponse(200, json_encode($responses[$this->n++]));
            }

            public function get(string $url, array $headers, int $timeout): \Ai\Http\HttpResponse
            {
                $this->url = $url;

                return new \Ai\Http\HttpResponse(200, '{"temp":20}');
            }
        };

        $result = $this->service($http)->run('helper', 'Какво е времето в Русе?');

        $this->assertSame(RunStatus::Completed, $result->status);
        $this->assertSame('20 градуса', $result->content);
        $this->assertSame(47, $result->usage->totalTokens());
        $this->assertSame('https://api.example.com/weather/%D0%A0%D1%83%D1%81%D0%B5', $http->url);
        $this->assertSame('weather', $http->sent[0]['tools'][0]['function']['name']);
        $this->assertStringContainsString('"temp":20', $http->sent[1]['messages'][3]['content']);
    }

    public function testInactiveAgentModelProviderOrToolAreNotUsed(): void
    {
        $this->saveWeatherTool(active: false);
        $this->agents->save('helper', 'Помощник', 'p', $this->modelId, ['weather'], active: false);

        try {
            $this->service(new FakeHttpClient([]))->run('helper', 'Hi');
            $this->fail('Очаква се изключение.');
        } catch (AiException $e) {
            $this->assertSame('Агентът не е наличен.', $e->getMessage());
        }

        $this->agents->save('helper', 'Помощник', 'p', $this->modelId, ['weather'], active: true);
        $this->assertTrue($this->agents->findByCode('helper')->runnable);

        $this->db->execute('UPDATE AiProviders SET active = 0');
        $this->assertFalse($this->agents->findByCode('helper')->runnable);
        $this->expectException(AiException::class);
        $this->service(new FakeHttpClient([]))->run('helper', 'Hi');
    }

    public function testUnknownAgentAndEmptyInput(): void
    {
        $this->agents->save('helper', 'Помощник', 'p', $this->modelId);

        foreach (['missing' => 'Hi', 'helper' => '  '] as $code => $input) {
            try {
                $this->service(new FakeHttpClient([]))->run($code, $input);
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInactiveToolIsNotOfferedToTheModel(): void
    {
        $this->saveWeatherTool(active: false);
        $this->agents->save('helper', 'Помощник', 'p', $this->modelId, ['weather']);
        $http = new FakeHttpClient(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'ok']]]]);

        $this->service($http)->run('helper', 'Hi');

        $this->assertArrayNotHasKey('tools', $http->payload);
    }
}
