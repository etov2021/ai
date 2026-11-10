<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

interface ToolInterface
{
    public function definition(): ToolDefinition;

    /**
     * Аргументите вече са валидирани спрямо схемата на инструмента.
     *
     * @param array<string, mixed> $arguments
     * @return string резултатът, който се връща на модела
     * @throws ToolException съобщението се показва на модела; не слагайте в него вътрешни детайли
     */
    public function execute(array $arguments): string;
}
