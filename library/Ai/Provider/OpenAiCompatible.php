<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;

/** Всеки сървър с OpenAI-съвместим /chat/completions: Ollama, LM Studio, vLLM, Mistral, Groq, Azure и др. */
final class OpenAiCompatible extends OpenAi
{
    // Локални сървъри (Ollama) работят без ключ.
    protected bool $requiresKey = false;

    public function __construct(
        HttpClientInterface $http,
        string $baseUrl,
        string $providerName,
        #[\SensitiveParameter] string $apiKey = '',
        int $timeout = 60,
    ) {
        parent::__construct($http, $apiKey, $baseUrl, $timeout, $providerName, 'max_tokens');
    }
}
