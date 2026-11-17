<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ProviderException;
use Ai\Security\Policy;

/** Списък на моделите на OpenAI-съвместим сървър (GET /models): OpenAI, Groq, OpenRouter, Mistral, LM Studio и др. */
final class OpenAiCatalog
{
    public const OPENAI_URL = 'https://api.openai.com/v1';

    // Не са чат модели: говор, вграждания, изображения, модерация. Грубо, но спестява ръчно триене.
    private const SKIP = '/(whisper|tts|embed|moderation|guard|dall-e|image|transcribe|audio|realtime|rerank|davinci|babbage)/i';

    public function __construct(private HttpClientInterface $http, private Policy $policy)
    {
    }

    /** @return list<array{model: string, label: string, contextWindow: ?int, maxOutputTokens: ?int}> */
    public function models(string $baseUrl, ?string $apiKey, int $timeout = 20): array
    {
        $root = rtrim($baseUrl, '/');
        $this->policy->assertBaseUrl($root);

        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== null && $apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        try {
            $response = $this->http->get($root . '/models', $headers, $timeout);
        } catch (HttpException) {
            throw new ProviderException('Сървърът не отговаря.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data) || !is_array($data['data'] ?? null)) {
            throw new ProviderException(sprintf('Сървърът върна неочакван отговор (HTTP %d).', $response->status), $response->status);
        }

        $models = [];
        foreach ($data['data'] as $m) {
            $id = is_array($m) && is_string($m['id'] ?? null) ? $m['id'] : '';
            if ($id === '' || preg_match(self::SKIP, $id) === 1 || ($m['active'] ?? true) === false) {
                continue;
            }
            $context = $m['context_window'] ?? $m['context_length'] ?? null;
            $output = $m['max_completion_tokens'] ?? $m['max_output_tokens'] ?? null;
            $models[$id] = [
                'model' => $id,
                'label' => mb_substr($id, 0, 100),
                'contextWindow' => is_int($context) && $context > 0 ? $context : null,
                'maxOutputTokens' => is_int($output) && $output > 0 ? $output : null,
            ];
        }

        ksort($models);

        return array_values($models);
    }
}
