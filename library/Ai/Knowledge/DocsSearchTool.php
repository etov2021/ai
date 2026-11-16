<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Agent\Agent;
use Ai\Contract\AgentAwareToolInterface;
use Ai\Contract\ToolInterface;
use Ai\Message\ToolDefinition;
use Ai\Tool\RetrieverTool;

/** Търсене по ключови думи в документите, до които агентът има достъп. */
final class DocsSearchTool implements AgentAwareToolInterface
{
    public const NAME = 'docs_search';

    /** @param ?list<int> $documentIds null = всички документи */
    public function __construct(private DocumentRepository $documents, private ?array $documentIds = null)
    {
    }

    public function forAgent(Agent $agent): ToolInterface
    {
        return new self($this->documents, $this->documents->idsForAgent($agent->id));
    }

    public function definition(): ToolDefinition
    {
        return $this->inner()->definition();
    }

    public function execute(array $arguments): string
    {
        return $this->inner()->execute($arguments);
    }

    private function inner(): RetrieverTool
    {
        return new RetrieverTool(
            self::NAME,
            'Търси в документите и връща най-подходящите откъси с името на документа. Заявката е с ключови думи (имена, термини, числа), не цял въпрос. За целия документ ползвай docs_read.',
            (new DocumentRetriever($this->documents))->scopedTo($this->documentIds),
            6,
        );
    }
}
