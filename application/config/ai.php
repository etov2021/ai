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
