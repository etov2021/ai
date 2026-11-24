<?php

// Само настройки без тайни. API ключовете се пазят криптирани в базата, а главният ключ е в променливата на средата AI_MASTER_KEY.
return [
    'ai' => [
        // Разрешава доставчици на локални/вътрешни адреси (напр. Ollama). Изключено по подразбиране заради SSRF.
        'allow_private_hosts' => true,
        // Класове на разширения (RAG, MCP, workflows, agent-to-agent), реализиращи Ai\Contract\ExtensionInterface.
        'extensions' => [\AiAdmin\Extension\GmailExtension::class, \AiAdmin\Extension\McpExtension::class, \AiAdmin\Extension\KnowledgeExtension::class, \AiAdmin\Extension\CalendarExtension::class],
        // Google Calendar (основният календар на свързания акаунт). Часовата зона се ползва за "днес", "утре" и за новите събития.
        'calendar' => [
            'timezone' => 'Europe/Sofia',
        ],
        // UI дизайнер: агентът, избран по подразбиране във формата (код на агент; празно = без предизбран).
        'design' => [
            'agent' => 'ui-designer'
        ],
        // MCP сървър за външни клиенти (адрес /mcp, достъп само с токен от AI → MCP достъп). enabled = false го изключва напълно.
        // allowed_origins: разрешени стойности на заглавието Origin (браузърни клиенти); клиентите без Origin не са засегнати.
        'mcp_server' => [
            'enabled' => true,
            'allowed_origins' => [],
            'max_result_chars' => 8000,
        ],
        // Одит (AI → Одит): колко дни се пазят записите; 0 = без изтриване.
        'audit' => [
            'retention_days' => 365,
        ],
        // Права по агент и инструмент (AI → Права). bypass_groups = алиаси на групи, които не се ограничават (напр. ['administrators']).
        'access' => [
            'bypass_groups' => [],
        ],
        // Фонови задачи и автоматизации (AI → Задачи, AI → Автоматизации; работник: cronjobs/jobs-run.php на всяка минута).
        // lock_seconds: след това време "работеща" задача се счита за изоставена; retention_days: колко се пазят приключилите.
        'jobs' => [
            'lock_seconds' => 900,
            'retention_days' => 30,
            'worker_max_jobs' => 20,
            'worker_seconds' => 50,
        ],
        // Търсене в документите по смисъл (вектори). provider = код на Gemini доставчик (AI → Доставчици) с API ключ; празно = само по ключови думи.
        // min_similarity (0-1) отрязва несвързани части; ако търсенето пропуска подходящи резултати, го намали.
        'knowledge' => [
            'embeddings' => [
                'provider' => 'gemini',
                'model' => 'gemini-embedding-001',
                'dimensions' => 768,
                'min_similarity' => 0.5,
            ],
        ],
        // MCP сървъри (Streamable HTTP). Агентът вижда само инструментите от allowed_tools; токенът се чете от променливата token_env.
        // Пример: 'docs' => ['url' => 'https://example.com/mcp', 'token_env' => 'MCP_DOCS_TOKEN', 'allowed_tools' => ['search'], 'timeout' => 20],
        'mcp' => [
            'servers' => [
                'mslearn' => [
                    'url' => 'https://learn.microsoft.com/api/mcp',
                    'allowed_tools' => ['microsoft_docs_search', 'microsoft_docs_fetch'],
                    'timeout' => 30,
                ],
            ],
        ],
        // Периодично четене на пощата (cronjobs/gmail-poll.php). agent = код на агент за анализ; празно = само известия.
        'gmail' => [
            'query' => 'in:inbox newer_than:2d',
            'agent' => 'mail-analyst',
            'limit' => 20,
            'analyze_per_run' => 5,
            // Агент, който търси срещи в писмата и ги записва като предложения в AI - Календар. Празно = изключено.
            'calendar_agent' => 'calendar-extractor',
            'calendar_per_run' => 5,
        ],
    ],
];
