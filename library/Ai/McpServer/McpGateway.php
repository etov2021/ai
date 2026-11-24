<?php

declare(strict_types=1);

namespace Ai\McpServer;

use Ai\Audit\AuditLog;
use Ai\Limits\RateLimiter;

/** HTTP политиката пред MCP сървъра: метод, Origin, токен, лимити и размер. Не знае нищо за Micro. */
final class McpGateway
{
    private const MAX_FAILED_AUTH_PER_MINUTE = 10;

    /**
     * @param list<string> $allowedOrigins Origin, различен от тези, се отхвърля (защита от DNS rebinding); клиенти без Origin (не браузъри) минават
     * @param \Closure(): int $clock
     */
    public function __construct(
        private McpTokenRepository $tokens,
        private McpServer $server,
        private RateLimiter $limiter,
        private \Closure $clock,
        private array $allowedOrigins = [],
        private int $maxBodyBytes = 65536,
        private ?AuditLog $audit = null,
    ) {
    }

    /** @return array{status: int, headers: array<string, string>, body: ?string} */
    public function handle(string $method, ?string $origin, ?string $authorization, string $ip, string $body): array
    {
        if (strtoupper($method) !== 'POST') {
            return self::reply(405, ['Allow' => 'POST']);
        }
        if ($origin !== null && $origin !== '' && !in_array($origin, $this->allowedOrigins, true)) {
            return self::reply(403);
        }

        $token = preg_match('/^Bearer\s+(\S{1,200})$/i', (string) $authorization, $m) === 1 ? $this->tokens->authenticate($m[1]) : null;
        if ($token === null) {
            // Ограничава налучкването и шума в базата; токените са дълги и случайни.
            $wait = $this->limiter->hit('mcpfail:' . $ip, self::MAX_FAILED_AUTH_PER_MINUTE, ($this->clock)());

            if ($wait === null) {
                $this->audit?->record('mcp.auth_failed', 'mcp', null, [], 'denied', ['type' => 'mcp', 'ip' => $ip]);
            }

            return $wait !== null ? self::reply(429, ['Retry-After' => (string) $wait]) : self::reply(401, ['WWW-Authenticate' => 'Bearer']);
        }

        // От тук нататък всички записи в одита за тази заявка са от името на токена.
        $this->audit?->actingAs(['type' => 'mcp', 'id' => $token->id, 'name' => $token->name, 'ip' => $ip]);

        if (strlen($body) > $this->maxBodyBytes) {
            return self::reply(413);
        }

        $wait = $this->limiter->hit('mcp:' . $token->id, $token->rateLimitPerMinute, ($this->clock)());
        if ($wait !== null) {
            return self::reply(429, ['Retry-After' => (string) $wait]);
        }

        $this->tokens->touch($token->id);
        $reply = $this->server->handle($body, $token);

        return $reply === null
            ? self::reply(202)
            : self::reply(200, ['Content-Type' => 'application/json'], json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** @param array<string, string> $headers */
    private static function reply(int $status, array $headers = [], ?string $body = null): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }
}
