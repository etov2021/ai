<?php

declare(strict_types=1);

namespace Ai\Tool;

use Ai\Contract\HttpClientInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;
use Ai\Security\Policy;

/**
 * Извиква външно API по предварително зададен шаблон на адрес.
 * Моделът подава само стойности на параметрите; хостът, методът и пътят не са във негова власт.
 *
 * Конфигурация: method (GET|POST), url (с {параметър} в пътя или заявката), allowedHosts, maxResultChars, timeoutSeconds.
 */
final class HttpTool implements ToolInterface
{
    private const DEFAULT_MAX_CHARS = 8000;

    /** @var list<string> */
    private array $allowedHosts;
    private string $method;
    private string $urlTemplate;
    private int $maxChars;
    private int $timeout;

    /** @param array<string, mixed> $config */
    public function __construct(
        private HttpClientInterface $http,
        private Policy $policy,
        private ToolDefinition $definition,
        array $config,
    ) {
        self::assertConfig($config);

        $this->method = strtoupper((string) $config['method']);
        $this->urlTemplate = (string) $config['url'];
        $this->allowedHosts = array_map('strtolower', $config['allowedHosts']);
        $this->maxChars = (int) ($config['maxResultChars'] ?? self::DEFAULT_MAX_CHARS);
        $this->timeout = (int) ($config['timeoutSeconds'] ?? 15);
    }

    /**
     * @param array<string, mixed> $config
     * @throws AiException
     */
    public static function assertConfig(array $config): void
    {
        if (!in_array(strtoupper((string) ($config['method'] ?? '')), ['GET', 'POST'], true)) {
            throw new AiException('Методът трябва да е GET или POST.');
        }
        $hosts = $config['allowedHosts'] ?? null;
        if (!is_array($hosts) || $hosts === [] || array_filter($hosts, static fn ($h): bool => !is_string($h) || $h === '') !== []) {
            throw new AiException('allowedHosts трябва да е непразен списък от хостове.');
        }
        $url = (string) ($config['url'] ?? '');
        // Заместването е позволено само след хоста, иначе моделът би избирал накъде да се изпрати заявката.
        if (preg_match('#^https?://([^/{}?\#@:]+)(?::\d+)?(?:[/?]|$)#i', $url, $m) !== 1) {
            throw new AiException('Невалиден шаблон на адрес: хостът не може да съдържа параметри.');
        }
        if (!in_array(strtolower($m[1]), array_map('strtolower', $hosts), true)) {
            throw new AiException('Хостът на адреса не е в allowedHosts.');
        }
        foreach (['maxResultChars' => [100, 50000], 'timeoutSeconds' => [1, 60]] as $key => [$min, $max]) {
            if (isset($config[$key]) && ((int) $config[$key] < $min || (int) $config[$key] > $max)) {
                throw new AiException(sprintf('%s трябва да е между %d и %d.', $key, $min, $max));
            }
        }
    }

    public function definition(): ToolDefinition
    {
        return $this->definition;
    }

    public function execute(array $arguments): string
    {
        $rest = $arguments;
        $url = preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', static function (array $m) use (&$rest): string {
            $value = $rest[$m[1]] ?? null;
            unset($rest[$m[1]]);
            if (!is_scalar($value)) {
                throw new ToolException(sprintf('Липсва стойност за "%s".', $m[1]));
            }
            // Точките не се кодират от rawurlencode и би позволили излизане от пътя.
            if ($value === '.' || $value === '..') {
                throw new ToolException(sprintf('Невалидна стойност за "%s".', $m[1]));
            }
            return rawurlencode(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }, $this->urlTemplate);

        $body = null;
        if ($this->method === 'GET' && $rest !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($rest, '', '&', PHP_QUERY_RFC3986);
        } elseif ($this->method === 'POST') {
            $body = json_encode($rest === [] ? new \stdClass() : $rest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Проверка в момента на извикване: и хостът, и IP адресите, към които се разрешава.
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!in_array($host, $this->allowedHosts, true)) {
            throw new ToolException('Адресът не е разрешен.');
        }
        try {
            $this->policy->assertBaseUrl($url);
            $response = $this->method === 'POST'
                ? $this->http->post($url, ['Content-Type' => 'application/json', 'Accept' => 'application/json'], (string) $body, $this->timeout)
                : $this->http->get($url, ['Accept' => 'application/json'], $this->timeout);
        } catch (HttpException) {
            throw new ToolException('Външната услуга не отговаря.');
        } catch (AiException) {
            throw new ToolException('Адресът не е разрешен.');
        }

        $text = mb_substr($response->body, 0, $this->maxChars);

        return sprintf("HTTP %d\n%s", $response->status, $text) . (mb_strlen($response->body) > $this->maxChars ? "\n[отговорът е съкратен]" : '');
    }
}
