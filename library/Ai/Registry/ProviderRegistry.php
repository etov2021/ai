<?php

declare(strict_types=1);

namespace Ai\Registry;

use Ai\Contract\HttpClientInterface;
use Ai\Contract\ProviderInterface;
use Ai\Exception\AiException;
use Ai\Provider\Anthropic;
use Ai\Provider\Gemini;
use Ai\Provider\OpenAi;
use Ai\Provider\OpenAiCompatible;
use Ai\Storage\ProviderConfig;

/** Свързва тип доставчик с фабрика; нов доставчик се добавя с register(), без промяна на това ядро. */
final class ProviderRegistry
{
    public const OLLAMA_DEFAULT_URL = 'http://localhost:11434/v1';

    /** @var array<string, array{factory: callable, requiresKey: bool, local: bool}> */
    private array $types = [];

    public function __construct(private HttpClientInterface $http)
    {
        $this->register('openai', fn (ProviderConfig $c, string $key): ProviderInterface => $c->baseUrl !== null
            ? new OpenAi($this->http, $key, $c->baseUrl, $c->timeoutSeconds)
            : new OpenAi($this->http, $key, timeout: $c->timeoutSeconds));

        $this->register('anthropic', fn (ProviderConfig $c, string $key): ProviderInterface => $c->baseUrl !== null
            ? new Anthropic($this->http, $key, $c->baseUrl, $c->timeoutSeconds)
            : new Anthropic($this->http, $key, timeout: $c->timeoutSeconds));

        $this->register('gemini', fn (ProviderConfig $c, string $key): ProviderInterface => $c->baseUrl !== null
            ? new Gemini($this->http, $key, $c->baseUrl, $c->timeoutSeconds)
            : new Gemini($this->http, $key, timeout: $c->timeoutSeconds));

        $this->register('openai_compatible', fn (ProviderConfig $c, string $key): ProviderInterface => new OpenAiCompatible(
            $this->http,
            $c->baseUrl ?? throw new AiException('Липсва базов адрес.'),
            $c->code,
            $key,
            $c->timeoutSeconds,
        ), requiresKey: false);

        $this->register('ollama', fn (ProviderConfig $c, string $key): ProviderInterface => new OpenAiCompatible(
            $this->http,
            $c->baseUrl ?? self::OLLAMA_DEFAULT_URL,
            $c->code,
            '',
            $c->timeoutSeconds,
        ), requiresKey: false, local: true);
    }

    /** @param callable(ProviderConfig, string): ProviderInterface $factory */
    public function register(string $type, callable $factory, bool $requiresKey = true, bool $local = false): void
    {
        $this->types[$type] = ['factory' => $factory, 'requiresKey' => $requiresKey, 'local' => $local];
    }

    /** Адресът, който типът ползва, когато не е зададен друг. Само локалните типове имат такъв, за да се проверява при запис. */
    public function defaultBaseUrl(string $type): ?string
    {
        return $type === 'ollama' ? self::OLLAMA_DEFAULT_URL : null;
    }

    public function isLocal(string $type): bool
    {
        return $this->types[$type]['local'] ?? false;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->types);
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function requiresKey(string $type): bool
    {
        return $this->types[$type]['requiresKey'] ?? throw new AiException(sprintf('Непознат тип доставчик "%s".', $type));
    }

    public function create(ProviderConfig $config, string $apiKey): ProviderInterface
    {
        $entry = $this->types[$config->type] ?? throw new AiException(sprintf('Непознат тип доставчик "%s".', $config->type));

        return ($entry['factory'])($config, $apiKey);
    }
}
