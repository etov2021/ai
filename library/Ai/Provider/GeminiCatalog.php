<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ProviderException;
use Ai\Security\Policy;

/** Списък на моделите на Gemini за даден ключ (GET /models), само тези с generateContent. */
final class GeminiCatalog
{
    public const DEFAULT_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const MAX_PAGES = 5;

    public function __construct(private HttpClientInterface $http, private Policy $policy)
    {
    }

    /** @return list<array{model: string, label: string, contextWindow: ?int, maxOutputTokens: ?int}> */
    public function models(#[\SensitiveParameter] string $apiKey, ?string $baseUrl = null, int $timeout = 20): array
    {
        $root = rtrim($baseUrl ?? self::DEFAULT_URL, '/');
        $this->policy->assertBaseUrl($root);

        $models = [];
        $pageToken = '';

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = $root . '/models?pageSize=200' . ($pageToken !== '' ? '&pageToken=' . rawurlencode($pageToken) : '');

            try {
                // Ключът е в заглавие, не в адреса, за да не попада в логове.
                $response = $this->http->get($url, ['x-goog-api-key' => $apiKey, 'Accept' => 'application/json'], $timeout);
            } catch (HttpException) {
                throw new ProviderException('Gemini не отговаря.');
            }

            $data = json_decode($response->body, true);
            if ($response->status !== 200 || !is_array($data)) {
                throw new ProviderException(sprintf('Gemini върна неочакван отговор (HTTP %d).', $response->status), $response->status);
            }

            foreach ((array) ($data['models'] ?? []) as $m) {
                $name = is_string($m['name'] ?? null) ? preg_replace('#^models/#', '', $m['name']) : '';
                if ($name === '' || !in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true)) {
                    continue;
                }
                $models[$name] = [
                    'model' => $name,
                    'label' => mb_substr((string) ($m['displayName'] ?? $name), 0, 100),
                    'contextWindow' => isset($m['inputTokenLimit']) ? (int) $m['inputTokenLimit'] : null,
                    'maxOutputTokens' => isset($m['outputTokenLimit']) ? (int) $m['outputTokenLimit'] : null,
                ];
            }

            $pageToken = (string) ($data['nextPageToken'] ?? '');
            if ($pageToken === '') {
                break;
            }
        }

        ksort($models);

        return array_values($models);
    }
}
