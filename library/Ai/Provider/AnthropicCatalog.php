<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ProviderException;
use Ai\Security\Policy;

/** Списък на моделите на Anthropic за даден ключ (GET /models). */
final class AnthropicCatalog
{
    public const DEFAULT_URL = 'https://api.anthropic.com/v1';
    private const API_VERSION = '2023-06-01';
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
        $after = '';

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = $root . '/models?limit=100' . ($after !== '' ? '&after_id=' . rawurlencode($after) : '');

            try {
                $response = $this->http->get($url, ['x-api-key' => $apiKey, 'anthropic-version' => self::API_VERSION, 'Accept' => 'application/json'], $timeout);
            } catch (HttpException) {
                throw new ProviderException('Anthropic не отговаря.');
            }

            $data = json_decode($response->body, true);
            if ($response->status !== 200 || !is_array($data) || !is_array($data['data'] ?? null)) {
                throw new ProviderException(sprintf('Anthropic върна неочакван отговор (HTTP %d).', $response->status), $response->status);
            }

            foreach ($data['data'] as $m) {
                $id = is_array($m) && is_string($m['id'] ?? null) ? $m['id'] : '';
                if ($id !== '') {
                    $models[$id] = ['model' => $id, 'label' => mb_substr((string) ($m['display_name'] ?? $id), 0, 100), 'contextWindow' => null, 'maxOutputTokens' => null];
                }
            }

            $after = (string) ($data['last_id'] ?? '');
            if (($data['has_more'] ?? false) !== true || $after === '') {
                break;
            }
        }

        ksort($models);

        return array_values($models);
    }
}
