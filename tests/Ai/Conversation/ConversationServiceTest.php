<?php

declare(strict_types=1);

namespace Ai\Tests\Conversation;

use Ai\Agent\AgentService;
use Ai\Conversation\ConversationService;
use Ai\Exception\AiException;
use Ai\Http\HttpResponse;
use Ai\Http\CurlClient;
use Ai\Registry\ProviderRegistry;
use Ai\Registry\ProviderResolver;
use Ai\Security\KeyVault;
use Ai\Security\Policy;
use Ai\Storage\AgentRepository;
use Ai\Storage\ConversationRepository;
use Ai\Storage\ModelRepository;
use Ai\Storage\ProviderRepository;
use Ai\Storage\RequestLogRepository;
use Ai\Storage\ToolRepository;
use Ai\Tests\FakeHttpClient;
use Ai\Tests\SqliteDatabase;
use Ai\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class ConversationServiceTest extends TestCase
{
    private SqliteDatabase $db;
    private Policy $policy;
    private AgentRepository $agents;
    private ConversationRepository $conversations;
    private RequestLogRepository $log;
    private ProviderRepository $providers;
    private int $modelId;

    protected function setUp(): void
    {
        $this->db = new SqliteDatabase();
        $this->policy = new Policy(false, static fn (string $h): array => ['93.184.216.34']);
        $this->providers = new ProviderRepository($this->db, new KeyVault(str_repeat('m', 32)), $this->policy, new ProviderRegistry(new CurlClient()));
        $provider = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');
        $this->modelId = (new ModelRepository($this->db, $this->policy))->save($provider->id, 'gpt-x', 'GPT X', 2, 10)->id;
        $this->agents = new AgentRepository($this->db, $this->policy);
        $this->agents->save('helper', 'Помощник', 'sys', $this->modelId);
        $this->agents->save('other', 'Друг', 'sys', $this->modelId);
        $this->conversations = new ConversationRepository($this->db);
        $this->log = new RequestLogRepository($this->db);
    }

    /** @param list<array<string, mixed>|int> $responses */
    private function service(FakeHttpClient $http, int $maxHistory = 40): ConversationService
    {
        $agentService = new AgentService(
            $this->agents,
            new ToolRepository($this->db, $this->policy),
            new ToolRegistry($http, $this->policy),
            new ProviderResolver($this->providers, new ProviderRegistry($http)),
            $this->log,
        );

        return new ConversationService($agentService, $this->agents, $this->conversations, $maxHistory);
    }

    private function http(string ...$answers): FakeHttpClient
    {
        return new class ($answers) extends FakeHttpClient {
            public array $sent = [];
            private int $n = 0;

            public function __construct(private array $answers)
            {
            }

            public function post(string $url, array $headers, string $body, int $timeout): HttpResponse
            {
                $this->sent[] = json_decode($body, true);

                return new HttpResponse(200, json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => ['content' => $this->answers[$this->n++]]]],
                    'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
                ]));
            }
        };
    }

    public function testNewConversationIsCreatedAndMessagesPersisted(): void
    {
        $http = $this->http('Здравей!');

        $reply = $this->service($http)->ask('helper', "  Как си\n  днес? ", userId: 5);

        $this->assertSame('Здравей!', $reply->result->content);
        $conversation = $this->conversations->find($reply->conversationId);
        $this->assertSame('Как си днес?', $conversation->title);
        $this->assertSame(5, $conversation->userId);

        $stored = $this->db->raw('SELECT role, content, seq FROM AiMessages ORDER BY seq');
        $this->assertSame(['user', 'assistant'], array_column($stored, 'role'));
        $this->assertSame([1, 2], array_map('intval', array_column($stored, 'seq')));

        $rows = $this->log->recent();
        $this->assertSame($reply->conversationId, (int) $rows[1]['conversationId']);
        $this->assertSame(5, (int) $rows[1]['userId']);
        $this->assertEqualsWithDelta(0.003, (float) $rows[1]['cost'], 1e-9);
    }

    public function testFollowUpSendsHistoryToTheModel(): void
    {
        $http = $this->http('Първи', 'Втори');
        $service = $this->service($http);

        $first = $service->ask('helper', 'Едно', userId: 5);
        $service->ask('helper', 'Две', $first->conversationId, 5);

        $messages = $http->sent[1]['messages'];
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($messages, 'role'));
        $this->assertSame('Първи', $messages[2]['content']);
        $this->assertCount(4, $this->db->raw('SELECT id FROM AiMessages'));
    }

    public function testConversationOfAnotherUserOrAgentIsNotAccessible(): void
    {
        $service = $this->service($this->http('a', 'b', 'c', 'd'));
        $id = $service->ask('helper', 'Тайна', userId: 5)->conversationId;

        foreach ([[$id, 6, 'helper'], [$id, null, 'helper'], [$id, 5, 'other'], [9999, 5, 'helper']] as [$cid, $uid, $agent]) {
            try {
                $service->ask($agent, 'Хайде', $cid, $uid);
                $this->fail('Очаква се изключение.');
            } catch (AiException $e) {
                $this->assertSame('Разговорът не е наличен.', $e->getMessage());
            }
        }
        $this->assertCount(2, $this->db->raw('SELECT id FROM AiMessages'));
    }

    public function testHistoryIsTrimmedAndStartsWithUserMessage(): void
    {
        $http = $this->http('a1', 'a2', 'a3');
        $service = $this->service($http, 3);
        $id = $service->ask('helper', 'u1', userId: 1)->conversationId;
        $service->ask('helper', 'u2', $id, 1);
        $service->ask('helper', 'u3', $id, 1);

        // от последните 3 (assistant a1, user u2, assistant a2) се пропуска водещият assistant
        $roles = array_column($http->sent[2]['messages'], 'content');
        $this->assertSame(['sys', 'u2', 'a2', 'u3'], $roles);
    }

    public function testFailedRunStoresNoMessages(): void
    {
        $http = new FakeHttpClient(['error' => ['message' => 'boom']], 500);

        try {
            $this->service($http)->ask('helper', 'Hi', userId: 1);
            $this->fail('Очаква се изключение.');
        } catch (\Ai\Exception\ProviderException) {
            $this->assertSame([], $this->db->raw('SELECT id FROM AiMessages'));
            $this->assertSame('error', $this->log->recent()[0]['status']);
        }
    }

    public function testToolMessagesRoundTripThroughStorage(): void
    {
        $conversation = $this->conversations->create(1, null, 'T');
        $this->conversations->append($conversation->id, [
            \Ai\Message\Message::user('?'),
            \Ai\Message\Message::assistant(null, [new \Ai\Message\ToolCall('c1', 'weather', ['city' => 'Русе'], null, 'sig')]),
            \Ai\Message\Message::toolResult('c1', 'weather', '{"t":20}'),
            \Ai\Message\Message::assistant('20 градуса'),
        ]);

        $history = $this->conversations->history($conversation->id, 10);

        $this->assertCount(4, $history);
        $this->assertSame(['city' => 'Русе'], $history[1]->toolCalls[0]->arguments);
        $this->assertSame('sig', $history[1]->toolCalls[0]->opaque);
        $this->assertSame('c1', $history[2]->toolCallId);
        $this->assertSame('weather', $history[2]->name);
    }
}
