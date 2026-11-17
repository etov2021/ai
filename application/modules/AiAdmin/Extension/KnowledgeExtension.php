<?php

namespace AiAdmin\Extension;

use Ai\Contract\ExtensionInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Extension\ExtensionRegistry;
use Ai\Knowledge\DocsListTool;
use Ai\Knowledge\DocsReadTool;
use Ai\Knowledge\DocsSearchTool;
use AiAdmin\Service\AiServices;

/** Инструменти docs_search, docs_list и docs_read върху библиотеката с документи. На агент се дават през редове в AiTools с type = builtin. */
class KnowledgeExtension implements ExtensionInterface, ToolProviderInterface
{
    public function name(): string
    {
        return 'knowledge';
    }

    public function register(ExtensionRegistry $registry): void
    {
        $registry->addToolProvider($this);
    }

    public function tools(): array
    {
        $services = AiServices::get();
        $documents = $services->documents();

        return [new DocsSearchTool($documents, null, $services->semanticSearch()), new DocsListTool($documents), new DocsReadTool($documents)];
    }
}
