<?php

declare(strict_types=1);

namespace Ai\Provider;

use Ai\Contract\HttpClientInterface;
use Ai\Contract\ProviderInterface;
use Ai\Exception\AiException;
use Ai\Exception\ProviderException;

abstract class AbstractProvider implements ProviderInterface
{
    protected bool $requiresKey = true;

    public function __construct(
        protected HttpClientInterface $http,
        #[\SensitiveParameter] protected string $apiKey,
        protected string $baseUrl,
        protected int $timeout = 60,
    ) {
        if ($this->requiresKey && $apiKey === '') {
            throw new AiException(sprintf('Липсва API ключ за доставчик "%s".', $this->name()));
        }
        if (preg_match('#^https?://#i', $baseUrl) !== 1) {
            throw new AiException('Базовият адрес трябва да започва с http:// или https://.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    // Ключът не бива да излиза през var_dump/print_r в логове и дебъг страници.
    public function __debugInfo(): array
    {
        return ['name' => $this->name(), 'baseUrl' => $this->baseUrl];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function post(string $path, array $headers, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->http->post($this->baseUrl . $path, $headers + ['Content-Type' => 'application/json'], $body, $this->timeout);

        $data = json_decode($response->body, true);

        if ($response->status >= 400) {
            $message = is_array($data) ? $this->errorMessage($data) : null;
            $message ??= mb_substr(trim($response->body), 0, 300);
            throw new ProviderException(
                sprintf('%s: HTTP %d: %s', $this->name(), $response->status, $message),
                $response->status,
                is_array($data) ? $this->errorCode($data) : null,
            );
        }
        if (!is_array($data)) {
            throw new ProviderException(sprintf('%s: невалиден JSON отговор.', $this->name()), $response->status);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    abstract protected function errorMessage(array $data): ?string;

    /** @param array<string, mixed> $data */
    protected function errorCode(array $data): ?string
    {
        return null;
    }

    /** @return array{0: array<string, mixed>, 1: ?string} аргументи и описание на грешка при разчитане */
    protected function parseArguments(mixed $raw): array
    {
        if (is_array($raw)) {
            return [$raw, null];
        }
        if ($raw === null || $raw === '') {
            return [[], null];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [[], 'Аргументите на инструмента не са валиден JSON обект.'];
        }

        return [$decoded, null];
    }
}
