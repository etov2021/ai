<?php

declare(strict_types=1);

namespace Ai\McpServer;

use Ai\Contract\ToolInterface;
use Ai\Exception\ToolException;
use Ai\Security\PiiMasker;
use Ai\Tool\SchemaValidator;

/** MCP сървър (JSON-RPC 2.0 през HTTP): initialize, ping, tools/list, tools/call. Без ресурси, подсказки и потоци. */
final class McpServer
{
    private const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
    // Инструментите за четене на документи не променят нищо; агентът може (напр. да предложи събитие).
    private const READ_ONLY = ['docs_search', 'docs_list', 'docs_read'];

    /** @param \Closure(McpToken): array<string, ToolInterface> $tools всички инструменти за токена по име; тук се филтрират по правата му */
    public function __construct(
        private \Closure $tools,
        private McpTokenRepository $tokens,
        private int $maxResultChars = 8000,
    ) {
    }

    /** @return ?array<mixed> отговорът за кодиране в JSON; null, когато няма какво да се върне (известия) */
    public function handle(string $body, McpToken $token): ?array
    {
        $message = json_decode($body, true);
        if (!is_array($message)) {
            return self::error(null, -32700, 'Невалиден JSON.');
        }

        if (array_is_list($message) && $message !== []) {
            $replies = array_values(array_filter(array_map(fn ($m) => $this->one($m, $token), $message)));

            return $replies === [] ? null : $replies;
        }

        return $this->one($message, $token);
    }

    /** @return ?array<string, mixed> */
    private function one(mixed $message, McpToken $token): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, -32600, 'Невалидна заявка.');
        }

        $isRequest = array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        if ($isRequest && !is_int($id) && !is_string($id)) {
            return self::error(null, -32600, 'Невалиден идентификатор.');
        }

        // Известия и отговори на клиента не се отговарят.
        if (!$isRequest || !is_string($message['method'] ?? null)) {
            return null;
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($message['method']) {
            'initialize' => self::result($id, [
                'protocolVersion' => in_array($params['protocolVersion'] ?? null, self::SUPPORTED_VERSIONS, true) ? $params['protocolVersion'] : self::SUPPORTED_VERSIONS[1],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'aiimpl', 'version' => '1.0'],
            ]),
            'ping' => self::result($id, new \stdClass()),
            'tools/list' => self::result($id, ['tools' => $this->list($token)]),
            'tools/call' => $this->call($id, $params, $token),
            default => self::error($id, -32601, 'Методът не се поддържа.'),
        };
    }

    /** @return list<array<string, mixed>> */
    private function list(McpToken $token): array
    {
        $list = [];
        foreach ($this->allowed($token) as $name => $tool) {
            $definition = $tool->definition();
            $list[] = [
                'name' => $name,
                'description' => $definition->description,
                'inputSchema' => $definition->schema(),
                'annotations' => ['readOnlyHint' => in_array($name, self::READ_ONLY, true), 'openWorldHint' => false],
            ];
        }

        return $list;
    }

    /** @return array<string, ToolInterface> */
    private function allowed(McpToken $token): array
    {
        return array_intersect_key(($this->tools)($token), array_flip($token->tools));
    }

    /** @param array<string, mixed> $params */
    private function call(int|string $id, array $params, McpToken $token): array
    {
        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $tool = $this->allowed($token)[$name] ?? null;
        if ($tool === null) {
            $this->tokens->audit($token->id, $name, 'denied', 0, 0);

            return self::error($id, -32602, 'Непознат инструмент.');
        }

        $arguments = $params['arguments'] ?? [];
        $started = microtime(true);
        $isError = false;

        $errors = is_array($arguments) ? SchemaValidator::validate($tool->definition()->parameters, $arguments) : ['аргументите трябва да са обект'];
        if ($errors !== []) {
            $text = 'Невалидни аргументи: ' . implode('; ', $errors);
            $isError = true;
        } else {
            try {
                $text = $tool->execute($arguments);
            } catch (ToolException $e) {
                $text = $e->getMessage();
                $isError = true;
            } catch (\Throwable) {
                // Вътрешните детайли (SQL, пътища) не излизат навън.
                $text = 'Вътрешна грешка при изпълнение на инструмента.';
                $isError = true;
            }
        }

        if ($token->maskPii) {
            $text = PiiMasker::mask($text);
        }
        if (mb_strlen($text) > $this->maxResultChars) {
            $text = mb_substr($text, 0, $this->maxResultChars) . "\n[резултатът е отрязан]";
        }

        $this->tokens->audit($token->id, $name, $isError ? 'error' : 'ok', (int) round((microtime(true) - $started) * 1000), mb_strlen($text));

        return self::result($id, ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError]);
    }

    private static function result(int|string $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private static function error(int|string|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
