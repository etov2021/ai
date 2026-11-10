<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ProviderException;
use Ai\Security\Policy;

/** Списък на изтеглените модели в Ollama (GET /api/tags). */
final class OllamaCatalog
{
    public function __construct(private HttpClientInterface $http, private Policy $policy)
    {
    }

    /**
     * @param string $baseUrl адресът на доставчика, напр. http://localhost:11434/v1
     * @return list<string> имена на модели, напр. "llama3.1:8b"
     */
    public function models(string $baseUrl, int $timeout = 15): array
    {
        // Адресът е записан от админ, но се проверява отново: DNS и конфигурацията може да са се променили.
        $this->policy->assertBaseUrl($baseUrl);
        $root = preg_replace('#/v1/?$#', '', rtrim($baseUrl, '/'));

        try {
            $response = $this->http->get($root . '/api/tags', ['Accept' => 'application/json'], $timeout);
        } catch (HttpException $e) {
            throw new ProviderException('Ollama не отговаря на ' . $root . '.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
            throw new ProviderException(sprintf('Ollama върна неочакван отговор (HTTP %d).', $response->status), $response->status);
        }

        $names = [];
        foreach ($data['models'] as $model) {
            if (is_string($model['name'] ?? null) && $model['name'] !== '') {
                $names[] = $model['name'];
            }
        }
        sort($names);

        return $names;
    }
}
