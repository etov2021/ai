<?php

declare(strict_types=1);

namespace Ai\Tool;

use Ai\Exception\ToolException;

/** Брои влагането на агенти, които викат други агенти; един екземпляр се споделя между всички AgentTool. */
final class CallDepth
{
    private int $depth = 0;

    // Агент може да вика агент, който вика агент: границата спира цикли и избухване на разходите.
    public function __construct(private int $max = 2)
    {
    }

    public function enter(): void
    {
        if ($this->depth >= $this->max) {
            throw new ToolException('Достигната е максималната дълбочина на повикване между агенти.');
        }
        $this->depth++;
    }

    public function leave(): void
    {
        $this->depth = max(0, $this->depth - 1);
    }
}
