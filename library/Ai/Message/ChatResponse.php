<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class ChatResponse
{
    /** @param list<ToolCall> $toolCalls */
    public function __construct(
        public ?string $content,
        public array $toolCalls,
        public FinishReason $finishReason,
        public Usage $usage,
        public string $model,
        public ?string $id = null,
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /** Съобщението на асистента във вида, в който се добавя обратно в историята. */
    public function toMessage(): Message
    {
        return Message::assistant($this->content, $this->toolCalls);
    }
}
