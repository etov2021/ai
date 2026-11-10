<?php

declare(strict_types=1);

namespace Ai\Extension;

use Ai\Contract\ExtensionInterface;
use Ai\Contract\RetrieverInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Contract\WorkflowInterface;
use Ai\Exception\AiException;
use Ai\Tool\ToolRegistry;

/** Регистър на разширенията: RAG източници, доставчици на инструменти (MCP), workflows. Ядрото не се променя. */
final class ExtensionRegistry
{
    /** @var array<string, RetrieverInterface> */
    private array $retrievers = [];

    /** @var array<string, WorkflowInterface> */
    private array $workflows = [];

    /** @var list<ToolProviderInterface> */
    private array $toolProviders = [];

    /** @var array<string, true> */
    private array $extensions = [];

    public function register(ExtensionInterface $extension): void
    {
        if (isset($this->extensions[$extension->name()])) {
            throw new AiException(sprintf('Разширението "%s" вече е регистрирано.', $extension->name()));
        }
        $this->extensions[$extension->name()] = true;
        $extension->register($this);
    }

    public function addRetriever(string $name, RetrieverInterface $retriever): void
    {
        $this->retrievers[$name] = $retriever;
    }

    public function retriever(string $name): RetrieverInterface
    {
        return $this->retrievers[$name] ?? throw new AiException(sprintf('Няма регистриран източник на знания "%s".', $name));
    }

    public function addWorkflow(string $name, WorkflowInterface $workflow): void
    {
        $this->workflows[$name] = $workflow;
    }

    public function workflow(string $name): WorkflowInterface
    {
        return $this->workflows[$name] ?? throw new AiException(sprintf('Няма регистриран workflow "%s".', $name));
    }

    public function addToolProvider(ToolProviderInterface $provider): void
    {
        $this->toolProviders[] = $provider;
    }

    /** Предоставя инструментите на разширенията като вградени; на агент се дават през ред в AiTools с type = builtin. */
    public function registerTools(ToolRegistry $tools): void
    {
        foreach ($this->toolProviders as $provider) {
            foreach ($provider->tools() as $tool) {
                $tools->registerBuiltin($tool);
            }
        }
    }
}
