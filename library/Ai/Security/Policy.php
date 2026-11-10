<?php

declare(strict_types=1);

namespace Ai\Security;

use Ai\Exception\AiException;

/** Валидира входни данни от админа преди запис; не пази състояние. */
final class Policy
{
    /** @var \Closure(string): list<string> */
    private \Closure $resolver;

    /**
     * @param bool $allowPrivateHosts разрешава локални сървъри (Ollama и др.) по http
     * @param ?\Closure(string): list<string> $resolver подменя DNS разрешаването в тестове
     */
    public function __construct(private bool $allowPrivateHosts = false, ?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static fn (string $host): array => gethostbynamel($host) ?: [];
    }

    /** Копие с разрешени локални адреси само за типове доставчици, които са локални по природа (Ollama). */
    public function allowingPrivateHosts(): self
    {
        return new self(true, $this->resolver);
    }

    public function assertCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $code) !== 1) {
            throw new AiException('Кодът трябва да е 2-40 знака: малки латински букви, цифри, _ и -, започва с буква.');
        }
    }

    public function assertModelId(string $model): void
    {
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9._:/-]{0,119}$#', $model) !== 1) {
            throw new AiException('Невалиден идентификатор на модел.');
        }
    }

    /** Защита от SSRF и от изпращане на ключ по нешифрирана връзка към публичен хост. */
    public function assertBaseUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new AiException('Адресът трябва да е валиден http(s) URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new AiException('Адресът не бива да съдържа потребител или парола.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : ($host === 'localhost' ? ['127.0.0.1'] : ($this->resolver)($host));
        if ($ips === []) {
            throw new AiException('Адресът не може да бъде разрешен.');
        }

        $allPrivate = true;
        foreach ($ips as $ip) {
            // Метаданните на облачни хостове (169.254.169.254) са забранени дори при разрешени локални адреси.
            if (str_starts_with($ip, '169.254.') || str_starts_with(strtolower($ip), 'fe80:') || $ip === '0.0.0.0') {
                throw new AiException('Адресът сочи към забранен мрежов диапазон.');
            }
            $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if (!$isPublic && !$this->allowPrivateHosts) {
                throw new AiException('Адресът сочи към вътрешна мрежа, която не е разрешена.');
            }
            $allPrivate = $allPrivate && !$isPublic;
        }

        if ($scheme === 'http' && !$allPrivate) {
            throw new AiException('Публичните адреси трябва да използват https.');
        }
    }
}
