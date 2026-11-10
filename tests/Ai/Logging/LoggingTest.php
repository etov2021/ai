<?php

declare(strict_types=1);

namespace Ai\Tests\Logging;

use Ai\Agent\Agent;
use Ai\Agent\AgentRunner;
use Ai\Agent\RunLogger;
use Ai\Contract\ProviderInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\ProviderException;
use Ai\Message\ChatRequest;
use Ai\Message\ChatResponse;
use Ai\Message\Message;
use Ai\Message\ToolCall;
use Ai\Message\ToolDefinition;
use Ai\Message\Usage;
use Ai\Pricing\CostCalculator;
use Ai\Security\Redactor;
use Ai\Storage\RequestLogRepository;
use Ai\Tests\ScriptedProvider;
use Ai\Tests\SqliteDatabase;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    private SqliteDatabase $db;
    private RequestLogRepository $log;

    protected function setUp(): void
    {
        $this->db = new SqliteDatabase();
        $this->log = new RequestLogRepository($this->db);
    }

    private function agent(): Agent
    {
        return new Agent(7, 'helper', 'H', 'sys', 'openai', 'gpt-x', null, null, 5, 60, true, ['echo'], 2.0, 10.0);
    }

    public function testCostCalculation(): void
    {
        $this->assertSame(0.007, CostCalculator::cost(new Usage(1000, 500), 2.0, 10.0));
        $this->assertSame(0.0, CostCalculator::cost(new Usage(1000, 500), 0.0, 0.0));
        $this->assertSame(12.0, CostCalculator::cost(new Usage(1_000_000, 1_000_000), 2.0, 10.0));
    }

    public function testRedactorMasksKeysAndSecrets(): void
    {
        $text = 'Incorrect API key sk-proj-abcdefgh12345678, header Bearer abcdefghijklmnop, key AIzaSyA1234567890abcdefghij, password=hunter22, "api_key": "zzzz9999"';
        $masked = Redactor::redact($text);

        foreach (['abcdefgh12345678', 'abcdefghijklmnop', 'SyA1234567890', 'hunter22', 'zzzz9999'] as $secret) {
            $this->assertStringNotContainsString($secret, $masked);
        }
        $this->assertStringContainsString('Incorrect API key', $masked);
        $this->assertSame(10, mb_strlen(Redactor::redact(str_repeat('я', 50), 10)));
    }

    public function testRunIsLoggedWithTokensCostToolsAndSummary(): void
    {
        $tool = new class implements ToolInterface {
            public function definition(): ToolDefinition
            {
                return new ToolDefinition('echo', 'd', ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']]);
            }

            public function execute(array $arguments): string
            {
                return 'secret-result';
            }
        };
        $provider = new ScriptedProvider([
            ScriptedProvider::calls([new ToolCall('c1', 'echo', ['text' => 'личен-текст'])], 1000, 500),
            ScriptedProvider::text('Готово', 2000, 100),
        ]);
        $logger = new RunLogger($this->log, $this->agent(), 11, 5);

        (new AgentRunner($provider, observer: $logger))->run($this->agent(), [$tool], [Message::user('?')]);

        $rows = $this->log->forRun($logger->runId);
        $this->assertSame(['llm', 'tool', 'llm', 'run'], array_column($rows, 'kind'));
        $this->assertSame(7, (int) $rows[0]['agentId']);
        $this->assertSame(11, (int) $rows[0]['conversationId']);
        $this->assertSame(5, (int) $rows[0]['userId']);
        $this->assertSame('gpt-x', $rows[0]['model']);
        $this->assertSame(1000, (int) $rows[0]['inputTokens']);
        $this->assertEqualsWithDelta(0.007, (float) $rows[0]['cost'], 1e-9);
        $this->assertSame('echo', $rows[1]['tool']);
        $this->assertSame('completed', $rows[3]['status']);
        $this->assertSame(0, (int) $rows[3]['inputTokens']);

        $total = $this->db->raw("SELECT SUM(cost) AS c FROM AiRequestLog WHERE kind = 'llm'")[0]['c'];
        $this->assertEqualsWithDelta(0.007 + 0.005, (float) $total, 1e-9);

        $dump = json_encode($rows, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('личен-текст', $dump);
        $this->assertStringNotContainsString('secret-result', $dump);
    }

    public function testProviderFailureIsLoggedRedactedAndRethrown(): void
    {
        $provider = new class implements ProviderInterface {
            public function name(): string
            {
                return 'x';
            }

            public function chat(ChatRequest $request): ChatResponse
            {
                throw new ProviderException('openai: HTTP 401: Incorrect API key provided: sk-proj-abcdefgh12345678', 401);
            }
        };
        $logger = new RunLogger($this->log, $this->agent());

        try {
            (new AgentRunner($provider, observer: $logger))->run($this->agent(), [], [Message::user('?')]);
            $this->fail('Очаква се изключение.');
        } catch (ProviderException) {
            $rows = $this->log->forRun($logger->runId);
            $this->assertCount(1, $rows);
            $this->assertSame('error', $rows[0]['status']);
            $this->assertStringNotContainsString('abcdefgh12345678', (string) $rows[0]['error']);
            $this->assertStringContainsString('401', (string) $rows[0]['error']);
        }
    }

    public function testBrokenObserverDoesNotAffectTheRun(): void
    {
        $failing = new RunLogger(new RequestLogRepository(new class extends \Ai\Tests\SqliteDatabase {
            public function execute(string $sql, array $params = []): int
            {
                throw new \RuntimeException('db down');
            }
        }), $this->agent());

        $result = (new AgentRunner(new ScriptedProvider([ScriptedProvider::text('ok')]), observer: $failing))->run($this->agent(), [], [Message::user('?')]);

        $this->assertSame('ok', $result->content);
    }
}
