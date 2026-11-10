<?php

declare(strict_types=1);

namespace Ai\Storage;

final readonly class ModelConfig
{
    public function __construct(
        public int $id,
        public int $providerId,
        public string $model,
        public string $label,
        public float $inputPricePerMillion,
        public float $outputPricePerMillion,
        public ?int $contextWindow,
        public ?int $maxOutputTokens,
        public bool $supportsTools,
        public bool $active,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['providerId'],
            (string) $row['model'],
            (string) $row['label'],
            (float) $row['inputPricePerMillion'],
            (float) $row['outputPricePerMillion'],
            $row['contextWindow'] !== null ? (int) $row['contextWindow'] : null,
            $row['maxOutputTokens'] !== null ? (int) $row['maxOutputTokens'] : null,
            (bool) $row['supportsTools'],
            (bool) $row['active'],
        );
    }
}
