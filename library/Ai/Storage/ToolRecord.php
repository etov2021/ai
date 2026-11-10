<?php

declare(strict_types=1);

namespace Ai\Storage;

final readonly class ToolRecord
{
    /**
     * @param array<string, mixed> $parameters JSON Schema на аргументите
     * @param array<string, mixed> $config
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $description,
        public string $type,
        public array $parameters,
        public array $config,
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
            (string) $row['description'],
            (string) $row['type'],
            json_decode((string) $row['parameters'], true) ?: ['type' => 'object', 'properties' => []],
            $row['config'] !== null && $row['config'] !== '' ? (json_decode((string) $row['config'], true) ?: []) : [],
            (bool) $row['active'],
        );
    }
}
