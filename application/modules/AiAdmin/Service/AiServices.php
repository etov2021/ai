<?php

namespace AiAdmin\Service;

use Ai\Agent\AgentService;
use Ai\Conversation\ConversationService;
use Ai\Contract\ExtensionInterface;
use Ai\Exception\AiException;
use Ai\Extension\ExtensionRegistry;
use Ai\Gmail\GmailAccountRepository;
use Ai\Gmail\GmailClient;
use Ai\Gmail\GmailOAuth;
use Ai\Gmail\GmailPoller;
use Ai\Gmail\MailRepository;
use Ai\Http\CurlClient;
use Ai\Limits\RateLimiter;
use Ai\Limits\UsageGuard;
use Ai\Provider\OllamaCatalog;
use Ai\Registry\ProviderRegistry;
use Ai\Registry\ProviderResolver;
use Ai\Security\KeyVault;
use Ai\Security\Policy;
use Ai\Storage\AgentRepository;
use Ai\Storage\BudgetRepository;
use Ai\Storage\ConversationRepository;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\MicroDatabase;
use Ai\Storage\ModelRepository;
use Ai\Storage\ProviderRepository;
use Ai\Storage\RequestLogRepository;
use Ai\Storage\ToolRepository;
use Ai\Storage\UsageRepository;
use Ai\Storage\WorkflowRepository;
use Ai\Tool\ToolRegistry;
use Ai\Workflow\WorkflowService;

/**
 * Сглобява библиотеката Ai върху базата на Micro. Обектите се създават лениво,
 * така че липсващ AI_MASTER_KEY не пречи на страниците, които не пипат ключове.
 */
final class AiServices
{
    private static ?self $instance = null;

    /** @var array<string, object> */
    private array $cache = [];

    private function __construct(private object $adapter)
    {
    }

    public static function get(): self
    {
        return self::$instance ??= new self(app('db'));
    }

    private function once(string $key, \Closure $factory): object
    {
        return $this->cache[$key] ??= $factory();
    }

    public function db(): DatabaseInterface
    {
        return $this->once('db', fn () => new MicroDatabase($this->adapter));
    }

    public function policy(): Policy
    {
        return $this->once('policy', fn () => new Policy((bool) config('ai.allow_private_hosts', false)));
    }

    public function registry(): ProviderRegistry
    {
        return $this->once('registry', fn () => new ProviderRegistry($this->http()));
    }

    public function http(): CurlClient
    {
        return $this->once('http', fn () => new CurlClient());
    }

    public function ollama(): OllamaCatalog
    {
        return $this->once('ollama', fn () => new OllamaCatalog($this->http(), $this->policy()->allowingPrivateHosts()));
    }

    /** @throws \Ai\Exception\AiException ако AI_MASTER_KEY липсва или е невалиден */
    public function providers(): ProviderRepository
    {
        return $this->once('providers', fn () => new ProviderRepository($this->db(), KeyVault::fromEnv(), $this->policy(), $this->registry()));
    }

    public function models(): ModelRepository
    {
        return $this->once('models', fn () => new ModelRepository($this->db(), $this->policy()));
    }

    public function agents(): AgentRepository
    {
        return $this->once('agents', fn () => new AgentRepository($this->db(), $this->policy()));
    }

    public function tools(): ToolRepository
    {
        return $this->once('tools', fn () => new ToolRepository($this->db(), $this->policy()));
    }

    public function budgets(): BudgetRepository
    {
        return $this->once('budgets', fn () => new BudgetRepository($this->db()));
    }

    public function usage(): UsageRepository
    {
        return $this->once('usage', fn () => new UsageRepository($this->db()));
    }

    public function logs(): RequestLogRepository
    {
        return $this->once('logs', fn () => new RequestLogRepository($this->db()));
    }

    public function conversations(): ConversationRepository
    {
        return $this->once('conversations', fn () => new ConversationRepository($this->db()));
    }

    /** Вградените PHP инструменти се регистрират тук или чрез разширения: $this->toolRegistry()->registerBuiltin(new ...). */
    public function toolRegistry(): ToolRegistry
    {
        return $this->once('toolRegistry', function () {
            $registry = new ToolRegistry($this->http(), $this->policy());
            $this->extensions()->registerTools($registry);

            return $registry;
        });
    }

    /** Разширенията са класове из config ai.extensions (реализират Ai\Contract\ExtensionInterface, без аргументи в конструктора). */
    public function extensions(): ExtensionRegistry
    {
        return $this->once('extensions', function () {
            $registry = new ExtensionRegistry();
            foreach ((array) config('ai.extensions', []) as $class) {
                if (!is_string($class) || !is_subclass_of($class, ExtensionInterface::class)) {
                    throw new AiException(sprintf('"%s" не е валидно разширение.', is_string($class) ? $class : gettype($class)));
                }
                $registry->register(new $class());
            }

            return $registry;
        });
    }

    public function agentService(): AgentService
    {
        return $this->once('agentService', fn () => new AgentService(
            $this->agents(),
            $this->tools(),
            $this->toolRegistry(),
            new ProviderResolver($this->providers(), $this->registry()),
            $this->logs(),
            new UsageGuard($this->budgets(), $this->usage(), new RateLimiter($this->db())),
        ));
    }

    public function chat(): ConversationService
    {
        return $this->once('chat', fn () => new ConversationService($this->agentService(), $this->agents(), $this->conversations()));
    }

    public function workflows(): WorkflowRepository
    {
        return $this->once('workflows', fn () => new WorkflowRepository($this->db(), $this->policy(), $this->agents()));
    }

    public function workflowService(): WorkflowService
    {
        return $this->once('workflowService', fn () => new WorkflowService($this->workflows(), $this->agentService(), $this->extensions()));
    }

    /** @throws \Ai\Exception\AiException ако AI_MASTER_KEY липсва или е невалиден */
    public function gmailAccounts(): GmailAccountRepository
    {
        return $this->once('gmailAccounts', fn () => new GmailAccountRepository($this->db(), KeyVault::fromEnv()));
    }

    public function gmailOAuth(): GmailOAuth
    {
        return $this->once('gmailOAuth', fn () => new GmailOAuth($this->http(), self::env('GOOGLE_CLIENT_ID'), self::env('GOOGLE_CLIENT_SECRET')));
    }

    /** Не чете базата и ключа при създаване: refresh токенът се взима чак при първо извикване на инструмент. */
    public function gmailClient(): GmailClient
    {
        return $this->once('gmailClient', fn () => new GmailClient(
            $this->http(),
            self::env('GOOGLE_CLIENT_ID'),
            self::env('GOOGLE_CLIENT_SECRET'),
            fn (): ?string => $this->gmailAccounts()->refreshToken(),
        ));
    }

    public function mailMessages(): MailRepository
    {
        return $this->once('mailMessages', fn () => new MailRepository($this->db()));
    }

    /** Агентът за анализ и заявката се задават в config ai.gmail; без агент се правят само известия. */
    public function mailPoller(): GmailPoller
    {
        return $this->once('mailPoller', fn () => new GmailPoller(
            $this->gmailClient(),
            $this->mailMessages(),
            $this->agentService(),
            (string) config('ai.gmail.agent', ''),
            (string) config('ai.gmail.query', 'in:inbox newer_than:2d'),
            (int) config('ai.gmail.limit', 20),
            (int) config('ai.gmail.analyze_per_run', 5),
        ));
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
