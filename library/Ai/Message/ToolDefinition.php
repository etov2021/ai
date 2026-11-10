<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class ToolDefinition
{
    /** @param array<string, mixed> $parameters JSON Schema от тип object */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters = ['type' => 'object', 'properties' => []],
    ) {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) !== 1) {
            throw new \InvalidArgumentException('Името на инструмента трябва да е 1-64 знака: букви, цифри, _ и -.');
        }
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        $schema = $this->parameters;
        $schema['type'] ??= 'object';
        // Празен масив би се кодирал като [] вместо {} и доставчиците го отхвърлят.
        if (empty($schema['properties'])) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }
}
