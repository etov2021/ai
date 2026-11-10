<?php

declare(strict_types=1);

namespace Ai\Tests\Agent;

use Ai\Agent\Agent;
use Ai\Agent\AgentRunner;
use Ai\Agent\RunStatus;
use Ai\Contract\ToolInterface;
use Ai\Exception\ToolException;
use Ai\Message\FinishReason;
use Ai\Message\Message;
use Ai\Message\Role;
use Ai\Message\ToolCall;
use Ai\Message\ToolDefinition;
use Ai\Tests\ScriptedProvider;
use PHPUnit\Framework\TestCase;

final class AgentRunnerTest extends TestCase
{
    private function agent(int $maxSteps = 5, int $timeout = 60): Agent
    {
        return new Agent(1, 'a', 'A', 'Бъди полезен', 'p', 'model-x', 0.3, 500, $maxSteps, $timeout, true, ['echo']);
    }

    /** @param callable(array): string $handler */
    private function tool(string $name, callable $handler, ?array $schema = null): ToolInterface
    {
        return new class ($name, $handler, $schema ?? ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']]) implements ToolInterface {
            public int $calls = 0;

            public function __construct(private string $name, private $handler, private array $schema)
            {
            }

            public function definition(): ToolDefinition
            {
                return new ToolDefinition($this->name, 'тест', $this->schema);
            }

            public function execute(array $arguments): string
            {
                $this->calls++;

                return ($this->handler)($arguments);
            }
        };
    }

    public function testPlainAnswerWithoutTools(): void
    {
        $provider = new ScriptedProvider([ScriptedProvider::text('Здравей', 7, 3)]);

        $result = (new AgentRunner($provider))->run($this->agent(), [], [Message::user('Hi')]);

        $this->assertSame(RunStatus::Completed, $result->status);
        $this->assertSame('Здравей', $result->content);
        $this->assertSame(10, $result->usage->totalTokens());
        $this->assertCount(1, $result->messages);
        $this->assertSame('Бъди полезен', $provider->requests[0]->system);
        $this->assertSame('model-x', $provider->requests[0]->model);
        $this->assertSame(0.3, $provider->requests[0]->temperature);
    }

    public function testToolLoopFeedsResultBackAndAccumulatesUsage(): void
    {
        $tool = $this->tool('echo', static fn (array $a): string => 'ехо: ' . $a['text']);
        $provider = new ScriptedProvider([
            ScriptedProvider::calls([new ToolCall('c1', 'echo', ['text' => 'ало'])], 10, 5),
            ScriptedProvider::text('Готово', 20, 6),
        ]);

        $result = (new AgentRunner($provider))->run($this->agent(), [$tool], [Message::user('?')]);

        $this->assertSame(RunStatus::Completed, $result->status);
        $this->assertSame(41, $result->usage->totalTokens());
        $this->assertCount(2, $result->steps);
        $this->assertSame('ехо: ало', $result->steps[0]->toolRuns[0]->result);
        $this->assertCount(1, $provider->requests[0]->tools);

        $second = $provider->requests[1]->messages;
        $this->assertSame(Role::Assistant, $second[1]->role);
        $this->assertSame(Role::Tool, $second[2]->role);
        $this->assertSame('ехо: ало', $second[2]->content);
        $this->assertSame('c1', $second[2]->toolCallId);
    }

    public function testUnknownOrForbiddenToolIsNotExecuted(): void
    {
        $tool = $this->tool('echo', static fn (): string => 'x');
        $provider = new ScriptedProvider([
            ScriptedProvider::calls([new ToolCall('c1', 'delete_everything', ['text' => 'x'])]),
            ScriptedProvider::text('ok'),
        ]);

        $result = (new AgentRunner($provider))->run($this->agent(), [$tool], [Message::user('?')]);

        $this->assertFalse($result->steps[0]->toolRuns[0]->ok);
        $this->assertSame(0, $tool->calls);
        $this->assertStringStartsWith('Грешка:', $provider->requests[1]->messages[2]->content);
    }

    public function testInvalidArgumentsAndParseErrorsAreNotExecuted(): void
    {
        $tool = $this->tool('echo', static fn (): string => 'x');
        $provider = new ScriptedProvider([
            ScriptedProvider::calls([
                new ToolCall('c1', 'echo', ['wrong' => 1]),
                new ToolCall('c2', 'echo', [], 'Аргументите не са валиден JSON обект.'),
            ]),
            ScriptedProvider::text('ok'),
        ]);

        $result = (new AgentRunner($provider))->run($this->agent(), [$tool], [Message::user('?')]);

        $this->assertSame(0, $tool->calls);
        $this->assertStringContainsString('Невалидни аргументи', $result->steps[0]->toolRuns[0]->result);
        $this->assertStringContainsString('валиден JSON', $result->steps[0]->toolRuns[1]->result);
    }

    public function testToolFailuresDoNotLeakInternalDetails(): void
    {
        $safe = $this->tool('safe', static fn (): string => throw new ToolException('Услугата е заета.'));
        $broken = $this->tool('broken', static fn (): string => throw new \RuntimeException('SQLSTATE password=secret'));
        $provider = new ScriptedProvider([
            ScriptedProvider::calls([new ToolCall('a', 'safe', ['text' => 'x']), new ToolCall('b', 'broken', ['text' => 'x'])]),
            ScriptedProvider::text('ok'),
        ]);

        $result = (new AgentRunner($provider))->run($this->agent(), [$safe, $broken], [Message::user('?')]);

        $this->assertSame('Услугата е заета.', $result->steps[0]->toolRuns[0]->result);
        $this->assertStringNotContainsString('secret', $result->steps[0]->toolRuns[1]->result);
    }

    public function testToolCallsPerStepAreCappedButEveryCallIsAnswered(): void
    {
        $tool = $this->tool('echo', static fn (): string => 'x');
        $calls = array_map(static fn (int $i): ToolCall => new ToolCall('c' . $i, 'echo', ['text' => 'x']), range(1, 7));
        $provider = new ScriptedProvider([ScriptedProvider::calls($calls), ScriptedProvider::text('ok')]);

        $result = (new AgentRunner($provider))->run($this->agent(), [$tool], [Message::user('?')]);

        $this->assertSame(5, $tool->calls);
        $this->assertCount(7, $result->steps[0]->toolRuns);
        $this->assertCount(2 + 7, $provider->requests[1]->messages);
    }

    public function testMaxStepsStopsTheLoopAndKeepsHistoryValid(): void
    {
        $tool = $this->tool('echo', static fn (): string => 'x');
        $loop = static fn (): \Ai\Message\ChatResponse => ScriptedProvider::calls([new ToolCall('c', 'echo', ['text' => 'x'])]);
        $provider = new ScriptedProvider([$loop(), $loop(), $loop()]);

        $result = (new AgentRunner($provider))->run($this->agent(maxSteps: 2), [$tool], [Message::user('?')]);

        $this->assertSame(RunStatus::MaxSteps, $result->status);
        $this->assertCount(2, $provider->requests);
        $this->assertSame(1, $tool->calls);
        $this->assertSame(Role::Tool, $result->messages[array_key_last($result->messages)]->role);
    }

    public function testTimeoutBetweenSteps(): void
    {
        $now = 0.0;
        $clock = function () use (&$now): float {
            return $now;
        };
        $tool = $this->tool('echo', function () use (&$now): string {
            $now += 100;
            return 'slow';
        });
        $provider = new ScriptedProvider([ScriptedProvider::calls([new ToolCall('c', 'echo', ['text' => 'x'])]), ScriptedProvider::text('never')]);

        $result = (new AgentRunner($provider, $clock))->run($this->agent(timeout: 60), [$tool], [Message::user('?')]);

        $this->assertSame(RunStatus::Timeout, $result->status);
        $this->assertCount(1, $provider->requests);
    }

    public function testFinishReasonsMapToStatus(): void
    {
        foreach ([[FinishReason::Length, RunStatus::Truncated], [FinishReason::ContentFilter, RunStatus::Blocked]] as [$finish, $status]) {
            $provider = new ScriptedProvider([ScriptedProvider::text('x', finish: $finish)]);
            $this->assertSame($status, (new AgentRunner($provider))->run($this->agent(), [], [Message::user('?')])->status);
        }
    }
}
