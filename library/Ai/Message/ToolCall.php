<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     * @param ?string $parseError попълнено, когато моделът е върнал невалиден JSON; такова извикване не бива да се изпълнява
     * @param ?string $opaque непрозрачни данни на доставчика, които трябва да се върнат непроменени (Gemini thoughtSignature)
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
        public ?string $parseError = null,
        public ?string $opaque = null,
    ) {
    }
}
