<?php

declare(strict_types=1);

namespace Ai\Tests\Message;

use Ai\Message\ChatRequest;
use Ai\Message\Message;
use Ai\Message\ToolDefinition;
use PHPUnit\Framework\TestCase;

final class ChatRequestTest extends TestCase
{
    public function testSystemMessagesAreMergedAndRemovedFromHistory(): void
    {
        $request = new ChatRequest('m', [Message::system('B'), Message::user('Hi')], 'A');

        $this->assertSame("A\n\nB", $request->system);
        $this->assertCount(1, $request->messages);
    }

    public function testRejectsEmptyModelOrOnlySystemMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChatRequest('m', [Message::system('only system')]);
    }

    public function testRejectsInvalidToolName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ToolDefinition('bad name!', 'x');
    }
}
