<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class Usage
{
    public function __construct(public int $inputTokens = 0, public int $outputTokens = 0)
    {
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
