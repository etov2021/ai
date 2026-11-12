<?php

// Само настройки без тайни. API ключовете се пазят криптирани в базата, а главният ключ е в променливата на средата AI_MASTER_KEY.
return [
    'ai' => [
        // Разрешава доставчици на локални/вътрешни адреси (напр. Ollama). Изключено по подразбиране заради SSRF.
        'allow_private_hosts' => true,
        // Класове на разширения (RAG, MCP, workflows, agent-to-agent), реализиращи Ai\Contract\ExtensionInterface.
        'extensions' => [\AiAdmin\Extension\GmailExtension::class],
        // Периодично четене на пощата (cronjobs/gmail-poll.php). agent = код на агент за анализ; празно = само известия.
        'gmail' => [
            'query' => 'in:inbox newer_than:2d',
            'agent' => '',
            'limit' => 20,
            'analyze_per_run' => 5,
        ],
    ],
];
