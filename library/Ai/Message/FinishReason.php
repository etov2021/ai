<?php

declare(strict_types=1);

namespace Ai\Message;

enum FinishReason: string
{
    case Stop = 'stop';
    case ToolCalls = 'tool_calls';
    case Length = 'length';
    case ContentFilter = 'content_filter';
    case Other = 'other';
}
