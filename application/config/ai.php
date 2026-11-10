<?php

// Само настройки без тайни. API ключовете се пазят криптирани в базата, а главният ключ е в променливата на средата AI_MASTER_KEY.
return [
    'ai' => [
        // Разрешава доставчици на локални/вътрешни адреси (напр. Ollama). Изключено по подразбиране заради SSRF.
        'allow_private_hosts' => false,
        // Класове на разширения (RAG, MCP, workflows, agent-to-agent), реализиращи Ai\Contract\ExtensionInterface.
        'extensions' => [],
    ],
];
