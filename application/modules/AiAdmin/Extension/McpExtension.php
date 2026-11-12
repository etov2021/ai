<?php

namespace AiAdmin\Extension;

use Ai\Contract\ExtensionInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Extension\ExtensionRegistry;
use AiAdmin\Service\AiServices;

/** Инструменти от MCP сървърите в config ai.mcp.servers. На агент се дават през ред в AiTools с type = builtin. */
class McpExtension implements ExtensionInterface, ToolProviderInterface
{
    public function name(): string
    {
        return 'mcp';
    }

    public function register(ExtensionRegistry $registry): void
    {
        $registry->addToolProvider($this);
    }

    public function tools(): array
    {
        return AiServices::get()->mcpProvider()->tools();
    }
}
