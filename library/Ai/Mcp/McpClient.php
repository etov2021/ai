<?php

declare(strict_types=1);

namespace Ai\Mcp;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Http\HttpResponse;
use Ai\Security\Policy;

/** Минимален MCP клиент (Streamable HTTP, JSON-RPC 2.0): initialize, tools/list и tools/call. Без stdio. */
final class McpClient
{
    private const PROTOCOL_VERSION = '2025-03-26';
    private const MAX_PAGES = 5;

    private bool $initialized = false;
    private ?string $sessionId = null;
    private int $nextId = 1;

    public function __construct(
        private HttpClientInterface $http,
        private Policy $policy,
        private string $url,
        #[\SensitiveParameter] private ?string $token = null,
        private int $timeout = 20,
    ) {
    }

    /** @return list<array{name: string, description: string, inputSchema: array<string, mixed>}> */
    public function listTools(): array
    {
        $tools = [];
        $params = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->request('tools/list', $params);
            foreach ((array) ($result['tools'] ?? []) as $tool) {
                if (!is_array($tool) || !is_string($tool['name'] ?? null)) {
                    continue;
                }
                $tools[] = [
                    'name' => $tool['name'],
                    'description' => (string) ($tool['description'] ?? ''),
                    'inputSchema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : ['type' => 'object', 'properties' => []],
                ];
            }
            $cursor = $result['nextCursor'] ?? null;
            if (!is_string($cursor) || $cursor === '') {
                break;
            }
            $params = ['cursor' => $cursor];
        }

        return $tools;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{text: string, isError: bool}
     */
    public function callTool(string $name, array $arguments): array
    {
        $result = $this->request('tools/call', ['name' => $name, 'arguments' => $arguments === [] ? new \stdClass() : $arguments]);

        $text = [];
        foreach ((array) ($result['content'] ?? []) as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                $text[] = $part['text'];
            } elseif (is_array($part)) {
                // Изображения и ресурси не се предават на модела.
                $text[] = '[' . (string) ($part['type'] ?? 'съдържание') . ' - пропуснато]';
            }
        }

        return ['text' => implode("\n", $text), 'isError' => (bool) ($result['isError'] ?? false)];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        $this->initialize();

        $id = $this->nextId++;

        return $this->send(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params === [] ? new \stdClass() : $params], $id);
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Проверка в момента на свързване: DNS може да се е променил след конфигурирането.
        $this->policy->assertBaseUrl($this->url);

        $id = $this->nextId++;
        $this->send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'initialize',
            'params' => ['protocolVersion' => self::PROTOCOL_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'aiimpl', 'version' => '1.0']],
        ], $id);
        $this->initialized = true;

        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function send(array $message, int $id): array
    {
        $response = $this->post($message);

        $reply = $this->parse($response, $id);
        if (isset($reply['error'])) {
            $code = is_array($reply['error']) ? (int) ($reply['error']['code'] ?? 0) : 0;
            throw new AiException(sprintf('MCP сървърът върна грешка (код %d).', $code));
        }

        return is_array($reply['result'] ?? null) ? $reply['result'] : [];
    }

    /** @param array<string, mixed> $message */
    private function post(array $message): HttpResponse
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'];
        if ($this->token !== null && $this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }
        if ($this->initialized) {
            $headers['MCP-Protocol-Version'] = self::PROTOCOL_VERSION;
        }

        try {
            $response = $this->http->post($this->url, $headers, (string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->timeout);
        } catch (HttpException) {
            throw new AiException('MCP сървърът не отговаря.');
        }

        if ($response->status < 200 || $response->status >= 300) {
            throw new AiException(sprintf('MCP сървърът върна HTTP %d.', $response->status));
        }
        if (isset($response->headers['mcp-session-id'])) {
            $this->sessionId = $response->headers['mcp-session-id'];
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function parse(HttpResponse $response, int $id): array
    {
        if (str_contains(strtolower($response->headers['content-type'] ?? ''), 'text/event-stream')) {
            foreach (preg_split('/\R\R/', $response->body) ?: [] as $event) {
                $data = [];
                foreach (preg_split('/\R/', $event) ?: [] as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $data[] = ltrim(substr($line, 5), ' ');
                    }
                }
                $decoded = json_decode(implode("\n", $data), true);
                if (is_array($decoded) && ($decoded['id'] ?? null) === $id) {
                    return $decoded;
                }
            }
            throw new AiException('MCP сървърът не върна отговор на заявката.');
        }

        $decoded = json_decode($response->body, true);
        if (!is_array($decoded) || ($decoded['id'] ?? null) !== $id) {
            throw new AiException('MCP сървърът върна невалиден отговор.');
        }

        return $decoded;
    }
}
