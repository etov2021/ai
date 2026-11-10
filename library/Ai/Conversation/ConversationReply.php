<?php

declare(strict_types=1);

namespace Ai\Conversation;

use Ai\Agent\RunResult;

final readonly class ConversationReply
{
    public function __construct(public int $conversationId, public RunResult $result)
    {
    }
}
