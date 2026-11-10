<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class Message
{
    /** @param list<ToolCall> $toolCalls */
    public function __construct(
        public Role $role,
        public ?string $content = null,
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {
    }

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }

    /** @param list<ToolCall> $toolCalls */
    public static function assistant(?string $content, array $toolCalls = []): self
    {
        return new self(Role::Assistant, $content, $toolCalls);
    }

    // name е задължително, защото Gemini свързва резултата с функцията по име, а не по id.
    public static function toolResult(string $toolCallId, string $name, string $content): self
    {
        return new self(Role::Tool, $content, [], $toolCallId, $name);
    }
}
