<?php

declare(strict_types=1);

namespace Ai\Mcp;

use Ai\Contract\HttpClientInterface;
use Ai\Contract\ToolInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Message\ToolDefinition;
use Ai\Security\Policy;

/**
 * Открива инструментите на конфигурираните MCP сървъри и дава на агентите само тези от allowed_tools.
 *
 * Сървър: ['url' => ..., 'token' => ?, 'allowed_tools' => [...], 'timeout' => 20, 'max_result_chars' => 8000]
 */
final class McpToolProvider implements ToolProviderInterface
{
    private const CACHE_OK = 600;
    // Кратко отрицателно кеширане: недостъпен сървър не бива да бави всяка заявка с таймаути.
    private const CACHE_FAIL = 60;
    private const SERVER_CODE = '/^[a-z][a-z0-9_-]{1,29}$/';

    /** @var array<string, McpClient> */
    private array $clients = [];

    /** @param array<string, array<string, mixed>> $servers */
    public function __construct(
        private array $servers,
        private HttpClientInterface $http,
        private Policy $policy,
        private ?string $cacheDir = null,
    ) {
    }

    public function tools(): array
    {
        $tools = [];

        foreach ($this->servers as $code => $server) {
            if (!$this->valid((string) $code, $server)) {
                continue;
            }
            $allowed = array_flip(array_map('strval', $server['allowed_tools']));
            $discovered = $this->discover((string) $code, false);

            foreach ($discovered['tools'] as $tool) {
                if (!isset($allowed[$tool['name']])) {
                    continue;
                }
                $schema = $tool['inputSchema'];
                unset($schema['$schema']);
                $schema['type'] = 'object';

                try {
                    $definition = new ToolDefinition(McpTool::localName((string) $code, $tool['name']), mb_substr($tool['description'], 0, 500), $schema);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                $tools[] = new McpTool($this->client((string) $code, $server), $definition, $tool['name'], (int) ($server['max_result_chars'] ?? 8000));
            }
        }

        return $tools;
    }

    /**
     * За админ страницата: състояние на всеки сървър и кои негови инструменти са разрешени.
     *
     * @return array<string, array{url: string, error: ?string, tools: list<array{name: string, local: string, description: string, allowed: bool}>}>
     */
    public function describe(bool $refresh = false): array
    {
        $out = [];

        foreach ($this->servers as $code => $server) {
            if (!$this->valid((string) $code, $server)) {
                $out[(string) $code] = ['url' => '', 'error' => 'Невалидна конфигурация (код, url и allowed_tools са задължителни).', 'tools' => []];
                continue;
            }
            $allowed = array_flip(array_map('strval', $server['allowed_tools']));
            $discovered = $this->discover((string) $code, $refresh);

            $out[(string) $code] = [
                'url' => (string) $server['url'],
                'error' => $discovered['error'],
                'tools' => array_map(static fn (array $t): array => [
                    'name' => $t['name'],
                    'local' => McpTool::localName((string) $code, $t['name']),
                    'description' => mb_substr($t['description'], 0, 200),
                    'allowed' => isset($allowed[$t['name']]),
                ], $discovered['tools']),
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $server */
    private function valid(string $code, array $server): bool
    {
        return preg_match(self::SERVER_CODE, $code) === 1
            && is_string($server['url'] ?? null) && $server['url'] !== ''
            && is_array($server['allowed_tools'] ?? null) && $server['allowed_tools'] !== [];
    }

    /** @param array<string, mixed> $server */
    private function client(string $code, array $server): McpClient
    {
        return $this->clients[$code] ??= new McpClient(
            $this->http,
            $this->policy,
            (string) $server['url'],
            isset($server['token']) && is_string($server['token']) ? $server['token'] : null,
            (int) ($server['timeout'] ?? 20),
        );
    }

    /** @return array{tools: list<array{name: string, description: string, inputSchema: array<string, mixed>}>, error: ?string} */
    private function discover(string $code, bool $refresh): array
    {
        $file = $this->cacheDir !== null ? rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'ai-mcp-' . $code . '.json' : null;

        if (!$refresh && $file !== null && is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && isset($cached['time'], $cached['tools']) && time() - (int) $cached['time'] < ($cached['error'] === null ? self::CACHE_OK : self::CACHE_FAIL)) {
                return ['tools' => $cached['tools'], 'error' => $cached['error'] ?? null];
            }
        }

        try {
            $result = ['tools' => $this->client($code, $this->servers[$code])->listTools(), 'error' => null];
        } catch (\Throwable $e) {
            $result = ['tools' => [], 'error' => $e instanceof \Ai\Exception\AiException ? $e->getMessage() : 'Грешка при свързване.'];
        }

        if ($file !== null && is_dir(dirname($file)) && is_writable(dirname($file))) {
            file_put_contents($file, json_encode($result + ['time' => time()], JSON_UNESCAPED_UNICODE), LOCK_EX);
        }

        return $result;
    }
}
