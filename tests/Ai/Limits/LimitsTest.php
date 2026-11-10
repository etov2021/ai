<?php

declare(strict_types=1);

namespace Ai\Tests\Limits;

use Ai\Agent\Agent;
use Ai\Agent\AgentService;
use Ai\Conversation\ConversationService;
use Ai\Exception\AiException;
use Ai\Exception\BudgetExceededException;
use Ai\Exception\RateLimitException;
use Ai\Http\CurlClient;
use Ai\Http\HttpResponse;
use Ai\Limits\RateLimiter;
use Ai\Limits\UsageGuard;
use Ai\Registry\ProviderRegistry;
use Ai\Registry\ProviderResolver;
use Ai\Security\KeyVault;
use Ai\Security\Policy;
use Ai\Storage\AgentRepository;
use Ai\Storage\BudgetRepository;
use Ai\Storage\ConversationRepository;
use Ai\Storage\ModelRepository;
use Ai\Storage\ProviderRepository;
use Ai\Storage\RequestLogRepository;
use Ai\Storage\ToolRepository;
use Ai\Storage\UsageRepository;
use Ai\Tests\FakeHttpClient;
use Ai\Tests\SqliteDatabase;
use Ai\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class LimitsTest extends TestCase
{
    private SqliteDatabase $db;
    private Policy $policy;
    private BudgetRepository $budgets;
    private UsageRepository $usage;
    private RequestLogRepository $log;
    private AgentRepository $agents;
    private ProviderRepository $providers;
    private ConversationRepository $conversations;
    private int $now;
    private int $modelId;

    protected function setUp(): void
    {
        $this->db = new SqliteDatabase();
        $this->policy = new Policy(false, static fn (string $h): array => ['93.184.216.34']);
        $this->budgets = new BudgetRepository($this->db);
        $this->usage = new UsageRepository($this->db);
        $this->log = new RequestLogRepository($this->db);
        $this->conversations = new ConversationRepository($this->db);
        $this->now = strtotime('2026-11-06 12:00:30');

        $this->providers = new ProviderRepository($this->db, new KeyVault(str_repeat('m', 32)), $this->policy, new ProviderRegistry(new CurlClient()));
        $provider = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');
        // 1000 входни + 100 изходни токена струват 0.003 EUR
        $this->modelId = (new ModelRepository($this->db, $this->policy))->save($provider->id, 'gpt-x', 'GPT X', 2, 10)->id;
        $this->agents = new AgentRepository($this->db, $this->policy);
        $this->agents->save('helper', 'Помощник', 'sys', $this->modelId);
    }

    private function http(): FakeHttpClient
    {
        return new class extends FakeHttpClient {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function post(string $url, array $headers, string $body, int $timeout): HttpResponse
            {
                $this->calls++;

                return new HttpResponse(200, json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'ok']]],
                    'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
                ]));
            }
        };
    }

    private function service(FakeHttpClient $http): AgentService
    {
        $guard = new UsageGuard($this->budgets, $this->usage, new RateLimiter($this->db), fn (): int => $this->now);

        return new AgentService(
            $this->agents,
            new ToolRepository($this->db, $this->policy),
            new ToolRegistry($http, $this->policy),
            new ProviderResolver($this->providers, new ProviderRegistry($http)),
            $this->log,
            $guard,
            maxInputChars: 100,
        );
    }

    public function testRateLimiterWindowAndRetryAfter(): void
    {
        $limiter = new RateLimiter($this->db);

        $this->assertNull($limiter->hit('b', 2, $this->now));
        $this->assertNull($limiter->hit('b', 2, $this->now + 1));
        $this->assertSame(29, $limiter->hit('b', 2, $this->now + 1));
        $this->assertNull($limiter->hit('other', 2, $this->now));
        $this->assertNull($limiter->hit('b', 2, $this->now + 60));
    }

    public function testOldRateLimitRowsAreCleanedUp(): void
    {
        $limiter = new RateLimiter($this->db);
        $limiter->hit('b', 5, $this->now - 7200);
        $limiter->hit('b', 5, $this->now);

        $this->assertCount(1, $this->db->raw('SELECT * FROM AiRateLimit'));
    }

    public function testPerUserRateLimitRejectsAndLogsWithoutCallingTheModel(): void
    {
        $this->budgets->save('user', '*', rateLimitPerMinute: 2);
        $http = $this->http();
        $service = $this->service($http);

        $service->run('helper', 'едно', userId: 5);
        $service->run('helper', 'две', userId: 5);
        try {
            $service->run('helper', 'три', userId: 5);
            $this->fail('Очаква се изключение.');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfterSeconds);
            $this->assertStringNotContainsString('user:5', $e->getMessage());
        }

        $service->run('helper', 'друг потребител', userId: 6);
        $this->assertSame(3, $http->calls);

        $rejected = array_values(array_filter($this->log->recent(), static fn (array $r): bool => $r['status'] === 'rejected'));
        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('user:5', $rejected[0]['error']);
    }

    public function testSpecificRuleOverridesWildcard(): void
    {
        $this->budgets->save('user', '*', rateLimitPerMinute: 1);
        $this->budgets->save('user', '7', rateLimitPerMinute: 5);
        $service = $this->service($this->http());

        for ($i = 0; $i < 3; $i++) {
            $service->run('helper', 'x', userId: 7);
        }
        $this->addToAssertionCount(1);

        $service->run('helper', 'x', userId: 8);
        $this->expectException(RateLimitException::class);
        $service->run('helper', 'x', userId: 8);
    }

    public function testDailyBudgetIsEnforcedAcrossRuns(): void
    {
        $this->budgets->save('agent', '*', dailyLimit: 0.005);
        $http = $this->http();
        $service = $this->service($http);

        $service->run('helper', 'едно');  // 0.003
        $service->run('helper', 'две');   // 0.006 >= 0.005

        try {
            $service->run('helper', 'три');
            $this->fail('Очаква се изключение.');
        } catch (BudgetExceededException $e) {
            $this->assertSame('Бюджетът за AI е изчерпан. Опитайте по-късно.', $e->getMessage());
            $this->assertStringContainsString('дневен', $e->reason);
        }
        $this->assertSame(2, $http->calls);
        $this->assertEqualsWithDelta(0.006, $this->usage->spent('agent', '1', '2026-11-06', '2026-11-06'), 1e-9);
        $this->assertEqualsWithDelta(0.006, $this->usage->spent('global', '', '2026-11-06', '2026-11-06'), 1e-9);
    }

    public function testMonthlyBudgetCountsWholeMonthButNotPreviousOne(): void
    {
        $this->budgets->save('global', '', monthlyLimit: 1.0);
        $this->usage->record('2026-10-31', 'global', '', new \Ai\Message\Usage(1, 1), 50.0);
        $this->usage->record('2026-11-01', 'global', '', new \Ai\Message\Usage(1, 1), 1.0);

        $this->expectException(BudgetExceededException::class);
        $this->service($this->http())->run('helper', 'x');
    }

    public function testPreviousMonthSpendDoesNotBlock(): void
    {
        $this->budgets->save('global', '', monthlyLimit: 1.0, dailyLimit: 1.0);
        $this->usage->record('2026-10-31', 'global', '', new \Ai\Message\Usage(1, 1), 50.0);

        $this->assertSame('ok', $this->service($this->http())->run('helper', 'x')->content);
    }

    public function testBudgetOfZeroBlocksEverything(): void
    {
        $this->budgets->save('provider', 'openai', dailyLimit: 0.0);

        $this->expectException(BudgetExceededException::class);
        $this->service($this->http())->run('helper', 'x');
    }

    public function testWarningIsLoggedAtThreshold(): void
    {
        $this->budgets->save('agent', '*', dailyLimit: 0.01, warnPercent: 50);
        $service = $this->service($this->http());

        $service->run('helper', 'едно'); // 0.003 < 50%
        $service->run('helper', 'две');  // admit вижда 0.003, още няма предупреждение
        $service->run('helper', 'три');  // admit вижда 0.006 >= 0.005

        $warnings = array_filter($this->log->recent(), static fn (array $r): bool => $r['status'] === 'warning');
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('50%', reset($warnings)['error']);
    }

    public function testBudgetIsCheckedBetweenSteps(): void
    {
        $this->budgets->save('agent', '*', dailyLimit: 0.003);
        // първата стъпка вече изразходва целия бюджет, а моделът иска инструмент
        $http = new class extends FakeHttpClient {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function post(string $url, array $headers, string $body, int $timeout): HttpResponse
            {
                $this->calls++;

                return new HttpResponse(200, json_encode([
                    'choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [['id' => 'c', 'function' => ['name' => 'ping', 'arguments' => '{}']]]]]],
                    'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
                ]));
            }
        };
        $tools = new ToolRepository($this->db, $this->policy);
        $tools->save('ping', 'Ping', 'Пинг', 'http', ['type' => 'object', 'properties' => []], ['method' => 'GET', 'url' => 'https://api.example.com/ping', 'allowedHosts' => ['api.example.com']], true);
        $this->agents->save('helper', 'Помощник', 'sys', $this->modelId, ['ping'], maxSteps: 5);

        try {
            $this->service($http)->run('helper', 'x');
            $this->fail('Очаква се изключение.');
        } catch (BudgetExceededException) {
            $this->assertSame(1, $http->calls);
            $llm = array_values(array_filter($this->log->recent(), static fn (array $r): bool => $r['kind'] === 'llm'));
            $this->assertSame(['error', 'ok'], array_column($llm, 'status'));
        }
    }

    public function testInputLengthLimit(): void
    {
        $this->expectException(AiException::class);
        $this->service($this->http())->run('helper', str_repeat('я', 101));
    }

    public function testRejectedNewConversationIsNotLeftBehind(): void
    {
        $this->budgets->save('global', '', rateLimitPerMinute: 0);
        $chat = new ConversationService($this->service($this->http()), $this->agents, $this->conversations);

        try {
            $chat->ask('helper', 'Hi', userId: 1);
            $this->fail('Очаква се изключение.');
        } catch (RateLimitException) {
            $this->assertSame([], $this->db->raw('SELECT id FROM AiConversations'));
        }
    }

    public function testRuleValidation(): void
    {
        foreach ([
            fn () => $this->budgets->save('galaxy', '*'),
            fn () => $this->budgets->save('user', 'bad key!'),
            fn () => $this->budgets->save('user', '*', rateLimitPerMinute: -1),
            fn () => $this->budgets->save('user', '*', dailyLimit: -5.0),
            fn () => $this->budgets->save('user', '*', warnPercent: 0),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->budgets->save('global', 'ignored', dailyLimit: 1.0);
        $this->budgets->save('global', '', dailyLimit: 2.0);
        $this->assertCount(1, $this->budgets->all());
    }

    public function testInactiveRuleIsIgnored(): void
    {
        $this->budgets->save('global', '', dailyLimit: 0.0, active: false);

        $this->assertSame('ok', $this->service($this->http())->run('helper', 'x')->content);
    }
}
