<?php

declare(strict_types=1);

namespace Ai\Storage;

final readonly class Conversation
{
    public function __construct(public int $id, public int $agentId, public ?int $userId, public string $title)
    {
    }
}
