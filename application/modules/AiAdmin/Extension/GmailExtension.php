<?php

namespace AiAdmin\Extension;

use Ai\Contract\ExtensionInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Extension\ExtensionRegistry;
use Ai\Gmail\GmailReadTool;
use Ai\Gmail\GmailSearchTool;
use AiAdmin\Service\AiServices;

/** Инструменти mail_search и mail_read (Gmail, само четене). На агент се дават през ред в AiTools с type = builtin. */
class GmailExtension implements ExtensionInterface, ToolProviderInterface
{
    public function name(): string
    {
        return 'gmail';
    }

    public function register(ExtensionRegistry $registry): void
    {
        $registry->addToolProvider($this);
    }

    public function tools(): array
    {
        $client = AiServices::get()->gmailClient();

        return [new GmailSearchTool($client), new GmailReadTool($client)];
    }
}
