<?php

namespace AiAdmin\Service;

use Ai\Agent\AgentService;
use Ai\Access\AccessPolicy;
use Ai\Audit\AuditLog;
use Ai\Automation\AutomationRepository;
use Ai\Automation\AutomationScheduler;
use Ai\Job\AgentJobHandler;
use Ai\Job\ClosureJobHandler;
use Ai\Job\JobRepository;
use Ai\Job\JobWorker;
use Ai\Job\PermanentJobFailure;
use Ai\Job\WorkflowJobHandler;
use Ai\Conversation\ConversationService;
use Ai\Design\DesignRepository;
use Ai\Design\DesignService;
use Ai\Design\LayoutBuilder;
use Ai\Design\LayoutWriter;
use Ai\Design\PackageImporter;
use Ai\Design\ReferenceFetcher;
use Ai\Design\TemplateEditor;
use Ai\Design\TemplateImporter;
use Ai\Design\TemplateRepository;
use Ai\Contract\ExtensionInterface;
use Ai\Exception\AiException;
use Ai\Extension\ExtensionRegistry;
use Ai\File\FileAnalyzer;
use Ai\File\FileTextExtractor;
use Ai\Gmail\GmailAccountRepository;
use Ai\Calendar\CalendarClient;
use Ai\Calendar\CalendarRepository;
use Ai\Google\GoogleToken;
use Ai\Gmail\GmailClient;
use Ai\Gmail\GmailOAuth;
use Ai\Gmail\GmailPoller;
use Ai\Gmail\MailRepository;
use Ai\Knowledge\DocumentImporter;
use Ai\Knowledge\DocumentIndexer;
use Ai\Knowledge\DocumentRepository;
use Ai\Knowledge\DocumentRetriever;
use Ai\Knowledge\DocsListTool;
use Ai\Knowledge\DocsReadTool;
use Ai\Knowledge\DocsSearchTool;
use Ai\McpServer\AskAgentTool;
use Ai\McpServer\McpGateway;
use Ai\McpServer\McpServer;
use Ai\McpServer\McpToken;
use Ai\McpServer\McpTokenRepository;
use Ai\Knowledge\EmbeddingRepository;
use Ai\Knowledge\GeminiEmbedder;
use Ai\Knowledge\SemanticSearch;
use Ai\Mcp\McpToolProvider;
use Ai\Http\CurlClient;
use Ai\Limits\RateLimiter;
use Ai\Limits\UsageGuard;
use Ai\Provider\AnthropicCatalog;
use Ai\Provider\GeminiCatalog;
use Ai\Provider\OllamaCatalog;
use Ai\Provider\OpenAiCatalog;
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

    public function geminiCatalog(): GeminiCatalog
    {
        return $this->once('geminiCatalog', fn () => new GeminiCatalog($this->http(), $this->policy()));
    }

    public function anthropicCatalog(): AnthropicCatalog
    {
        return $this->once('anthropicCatalog', fn () => new AnthropicCatalog($this->http(), $this->policy()));
    }

    public function openAiCatalog(): OpenAiCatalog
    {
        return $this->once('openAiCatalog', fn () => new OpenAiCatalog($this->http(), $this->policy()));
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
            access: $this->access(),
            audit: $this->audit(),
            currentUser: fn (): ?int => $this->auditActor()['id'],
        ));
    }

    public function audit(): AuditLog
    {
        return $this->once('audit', fn () => new AuditLog($this->db(), time(...), $this->auditActor(...), (int) config('ai.audit.retention_days', 365)));
    }

    public function jobs(): JobRepository
    {
        return $this->once('jobs', fn () => new JobRepository($this->db(), time(...), (int) config('ai.jobs.lock_seconds', 900)));
    }

    /** Обработчици по тип задача; нови типове се добавят тук. */
    public function jobWorker(): JobWorker
    {
        return $this->once('jobWorker', fn () => new JobWorker($this->jobs(), [
            'agent' => new AgentJobHandler($this->agentService()),
            'workflow' => new WorkflowJobHandler($this->workflowService()),
            'embed_documents' => new ClosureJobHandler(function (): string {
                $indexer = $this->documentIndexer();
                if ($indexer === null) {
                    throw new PermanentJobFailure('Търсенето по смисъл не е настроено (ai.knowledge.embeddings.provider).');
                }
                $result = $indexer->run(2000);

                return sprintf('Индексирани части: %d, оставащи: %d%s', $result['indexed'], $result['remaining'], $result['error'] !== null ? ', спряно: ' . $result['error'] : '');
            }),
            'gmail_poll' => new ClosureJobHandler(function (): string {
                $result = $this->mailPoller()->run();

                return sprintf('Намерени: %d, нови: %d, анализирани: %d, неуспешни: %d, предложени събития: %d', $result['found'], $result['added'], $result['analyzed'], $result['failed'], $result['proposed']);
            }),
        ], time(...), $this->audit()));
    }

    public function automations(): AutomationRepository
    {
        return $this->once('automations', fn () => new AutomationRepository($this->db(), time(...)));
    }

    public function automationScheduler(): AutomationScheduler
    {
        return $this->once('automationScheduler', fn () => new AutomationScheduler($this->automations(), $this->jobs(), time(...), $this->audit()));
    }

    public function groupRoles(): GroupRoles
    {
        return $this->once('groupRoles', fn () => new GroupRoles($this->db()));
    }

    /** Права по агент и инструмент; групите в ai.access.bypass_groups (алиаси) не се ограничават. */
    public function access(): AccessPolicy
    {
        return $this->once('access', fn () => new AccessPolicy(
            $this->db(),
            $this->groupRoles()->rolesOf(...),
            array_values(array_filter((array) config('ai.access.bypass_groups', []), 'is_string')),
        ));
    }

    /** @return array{type: string, id: ?int, name: ?string, ip: ?string} */
    private function auditActor(): array
    {
        // Само REMOTE_ADDR: заглавия като X-Forwarded-For се подправят от клиента.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;

        try {
            $identity = function_exists('identity') ? identity() : null;
        } catch (\Throwable) {
            $identity = null;
        }
        if ($identity) {
            return ['type' => 'user', 'id' => (int) $identity->getId(), 'name' => (string) $identity->getUsername(), 'ip' => $ip];
        }

        return PHP_SAPI === 'cli'
            ? ['type' => 'cron', 'id' => null, 'name' => basename((string) ($_SERVER['argv'][0] ?? 'cli')), 'ip' => null]
            : ['type' => 'system', 'id' => null, 'name' => null, 'ip' => $ip];
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
    public function googleToken(): GoogleToken
    {
        return $this->once('googleToken', fn () => new GoogleToken(
            $this->http(),
            self::env('GOOGLE_CLIENT_ID'),
            self::env('GOOGLE_CLIENT_SECRET'),
            fn (): ?string => $this->gmailAccounts()->refreshToken(),
        ));
    }

    public function gmailClient(): GmailClient
    {
        return $this->once('gmailClient', fn () => new GmailClient($this->http(), $this->googleToken(), fn (): array => $this->gmailAccounts()->scopes()));
    }

    /** Без повторни опити: при грешка след изпращане събитието може да е създадено, и повторението ще го дублира. */
    public function calendarClient(): CalendarClient
    {
        return $this->once('calendarClient', fn () => new CalendarClient(
            new CurlClient(0),
            $this->googleToken(),
            (string) config('ai.calendar.timezone', 'Europe/Sofia'),
            fn (): array => $this->gmailAccounts()->scopes(),
        ));
    }

    public function calendarProposals(): CalendarRepository
    {
        return $this->once('calendarProposals', fn () => new CalendarRepository($this->db()));
    }

    /** Токените идват от променливите на средата, посочени в token_env; без повторни опити, за да не се изпълни инструмент два пъти. */
    public function mcpProvider(): McpToolProvider
    {
        return $this->once('mcpProvider', function () {
            $servers = [];
            foreach ((array) config('ai.mcp.servers', []) as $code => $server) {
                if (!is_array($server)) {
                    continue;
                }
                if (isset($server['token_env'])) {
                    $server['token'] = self::env((string) $server['token_env']);
                }
                $servers[(string) $code] = $server;
            }

            return new McpToolProvider($servers, new CurlClient(0), $this->policy(), data_path('cache'));
        });
    }

    public function documents(): DocumentRepository
    {
        return $this->once('documents', fn () => new DocumentRepository($this->db()));
    }

    public function documentImporter(): DocumentImporter
    {
        return $this->once('documentImporter', fn () => new DocumentImporter(new FileTextExtractor(), $this->documents()));
    }

    public function documentRetriever(): DocumentRetriever
    {
        return $this->once('documentRetriever', fn () => new DocumentRetriever($this->documents(), null, $this->semanticSearch()));
    }

    public function embeddings(): EmbeddingRepository
    {
        return $this->once('embeddings', fn () => new EmbeddingRepository($this->db()));
    }

    /** null, ако config ai.knowledge.embeddings.provider е празен. Ключът се взима от посочения доставчик чак при първа заявка. */
    public function embedder(): ?GeminiEmbedder
    {
        $config = (array) config('ai.knowledge.embeddings', []);
        $code = (string) ($config['provider'] ?? '');
        if ($code === '') {
            return null;
        }

        return $this->once('embedder', fn () => new GeminiEmbedder(
            $this->http(),
            $this->policy(),
            fn (): string => $this->providers()->apiKey($code) ?? throw new AiException(sprintf('За доставчика "%s" няма записан API ключ.', $code)),
            (string) ($config['model'] ?? 'gemini-embedding-001'),
            (int) ($config['dimensions'] ?? 768),
        ));
    }

    public function semanticSearch(): ?SemanticSearch
    {
        $embedder = $this->embedder();

        return $embedder === null ? null : $this->once('semanticSearch', fn () => new SemanticSearch(
            $this->embeddings(),
            $embedder,
            (float) config('ai.knowledge.embeddings.min_similarity', 0.0),
        ));
    }

    public function documentIndexer(): ?DocumentIndexer
    {
        $embedder = $this->embedder();

        return $embedder === null ? null : $this->once('documentIndexer', fn () => new DocumentIndexer($this->embeddings(), $embedder));
    }

    public function mcpTokens(): McpTokenRepository
    {
        return $this->once('mcpTokens', fn () => new McpTokenRepository($this->db(), time(...), $this->audit()));
    }

    /** Външните клиенти получават само тези четири инструмента и само в рамките на правата на своя токен. */
    public function mcpGateway(): McpGateway
    {
        return $this->once('mcpGateway', fn () => new McpGateway(
            $this->mcpTokens(),
            new McpServer(
                fn (McpToken $t): array => [
                    'docs_search' => new DocsSearchTool($this->documents(), $t->documentIds, $this->semanticSearch()),
                    'docs_list' => new DocsListTool($this->documents(), $t->documentIds),
                    'docs_read' => new DocsReadTool($this->documents(), $t->documentIds),
                    'ask_agent' => new AskAgentTool($this->agentService(), (string) $t->agentCode),
                ],
                $this->mcpTokens(),
                (int) config('ai.mcp_server.max_result_chars', 8000),
            ),
            new RateLimiter($this->db()),
            time(...),
            array_values(array_filter((array) config('ai.mcp_server.allowed_origins', []), 'is_string')),
            audit: $this->audit(),
        ));
    }

    public function designs(): DesignRepository
    {
        return $this->once('designs', fn () => new DesignRepository($this->db()));
    }

    public function designService(): DesignService
    {
        return $this->once('designService', fn () => new DesignService(
            $this->agentService(),
            $this->designs(),
            $this->referenceFetcher(),
            $this->templates(),
        ));
    }

    /** Образците са произволни адреси от админа: строга политика (само публични https), независимо от allow_private_hosts. */
    public function referenceFetcher(): ReferenceFetcher
    {
        return $this->once('referenceFetcher', fn () => new ReferenceFetcher(new CurlClient(0, 10, 500, null, 1_500_000), new Policy(false)));
    }

    /** Файловете на пакетите са в data/design/templates, извън public. */
    public function templates(): TemplateRepository
    {
        return $this->once('templates', fn () => new TemplateRepository($this->db(), data_path('design/templates')));
    }

    public function templateImporter(): TemplateImporter
    {
        return $this->once('templateImporter', fn () => new TemplateImporter($this->templates(), new PackageImporter(), $this->referenceFetcher()));
    }

    public function templateEditor(): TemplateEditor
    {
        return $this->once('templateEditor', fn () => new TemplateEditor($this->agentService(), $this->templates(), $this->templateImporter()));
    }

    public function layoutBuilder(): LayoutBuilder
    {
        return $this->once('layoutBuilder', fn () => new LayoutBuilder());
    }

    /** Единственият път за запис: application/resources/layouts/<име> и public/templates/<име>. Админът не се пипа. */
    public function layoutWriter(): LayoutWriter
    {
        return $this->once('layoutWriter', fn () => new LayoutWriter(app_path('resources/layouts'), public_path(), data_path('design/backups')));
    }

    public function fileAnalyzer(): FileAnalyzer
    {
        return $this->once('fileAnalyzer', fn () => new FileAnalyzer(new FileTextExtractor(), $this->agentService()));
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
            $this->calendarClient(),
            $this->calendarProposals(),
            (string) config('ai.gmail.calendar_agent', ''),
            (int) config('ai.gmail.calendar_per_run', 5),
        ));
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
