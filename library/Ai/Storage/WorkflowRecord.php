<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Exception\AiException;
use Ai\Workflow\WorkflowDefinition;

final readonly class WorkflowRecord
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public WorkflowDefinition $definition,
        public bool $active,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = json_decode((string) $row['definition'], true);
        if (!is_array($data)) {
            throw new AiException(sprintf('Описанието на работен поток "%s" е повредено.', (string) $row['code']));
        }

        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            WorkflowDefinition::fromArray($data),
            (int) $row['active'] === 1,
        );
    }
}
