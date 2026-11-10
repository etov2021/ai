<?php

declare(strict_types=1);

namespace Ai\Storage;

/** Настройки на доставчик. Не съдържа API ключа, само дали има такъв и маска за показване. */
final readonly class ProviderConfig
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $type,
        public ?string $baseUrl,
        public bool $hasApiKey,
        public ?string $apiKeyHint,
        public int $timeoutSeconds,
        public bool $active,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['type'],
            $row['baseUrl'] !== null && $row['baseUrl'] !== '' ? (string) $row['baseUrl'] : null,
            !empty($row['apiKeyEnc']),
            $row['apiKeyHint'] !== null ? (string) $row['apiKeyHint'] : null,
            (int) $row['timeoutSeconds'],
            (bool) $row['active'],
        );
    }
}
