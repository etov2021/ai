<?php

declare(strict_types=1);

namespace Ai\Tests;

use Ai\Contract\ProviderInterface;
use Ai\Message\ChatRequest;
use Ai\Message\ChatResponse;
use Ai\Message\FinishReason;
use Ai\Message\ToolCall;
use Ai\Message\Usage;

/** Връща предварително зададени отговори и пази получените заявки. */
final class ScriptedProvider implements ProviderInterface
{
    /** @var list<ChatRequest> */
    public array $requests = [];

    /** @param list<ChatResponse> $script */
    public function __construct(private array $script)
    {
    }

    public function name(): string
    {
        return 'scripted';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;

        return array_shift($this->script) ?? throw new \LogicException('Скриптът е изчерпан.');
    }

    public static function text(string $text, int $in = 10, int $out = 5, FinishReason $finish = FinishReason::Stop): ChatResponse
    {
        return new ChatResponse($text, [], $finish, new Usage($in, $out), 'm');
    }

    /** @param list<ToolCall> $calls */
    public static function calls(array $calls, int $in = 10, int $out = 5, ?string $text = null): ChatResponse
    {
        return new ChatResponse($text, $calls, FinishReason::ToolCalls, new Usage($in, $out), 'm');
    }
}
