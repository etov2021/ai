<?php

declare(strict_types=1);

namespace Ai\Security;

/** Маскира ключове и пароли в текст, който отива в логове. */
final class Redactor
{
    private const PATTERNS = [
        '/\bsk-[A-Za-z0-9_\-]{8,}/' => 'sk-***',
        '/\bAIza[0-9A-Za-z_\-]{20,}/' => 'AIza***',
        '/\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}/i' => 'Bearer ***',
        '/\b(api[_-]?key|token|secret|password|passwd|authorization)(["\']?\s*[:=]\s*["\']?)[^\s"\',;&]{4,}/i' => '$1$2***',
    ];

    public static function redact(string $text, int $maxLength = 500): string
    {
        return mb_substr(preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text) ?? '', 0, $maxLength);
    }
}
