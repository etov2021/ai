<?php

declare(strict_types=1);

namespace Ai\Message;

final readonly class ChatRequest
{
    public ?string $system;

    /** @var list<Message> */
    public array $messages;

    /**
     * @param list<Message> $messages
     * @param list<ToolDefinition> $tools
     */
    public function __construct(
        public string $model,
        array $messages,
        ?string $system = null,
        public array $tools = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
    ) {
        if ($model === '') {
            throw new \InvalidArgumentException('Моделът е задължителен.');
        }

        // Системните съобщения се събират на едно място: Anthropic и Gemini ги искат извън списъка със съобщения.
        $systemParts = $system !== null && $system !== '' ? [$system] : [];
        $rest = [];
        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                if ($message->content !== null && $message->content !== '') {
                    $systemParts[] = $message->content;
                }
                continue;
            }
            $rest[] = $message;
        }

        if ($rest === []) {
            throw new \InvalidArgumentException('Заявката трябва да съдържа поне едно несистемно съобщение.');
        }
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new \InvalidArgumentException('maxTokens трябва да е положително число.');
        }

        $this->system = $systemParts === [] ? null : implode("\n\n", $systemParts);
        $this->messages = $rest;
    }
}
