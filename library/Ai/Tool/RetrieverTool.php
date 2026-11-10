<?php

declare(strict_types=1);

namespace Ai\Tool;

use Ai\Contract\RetrieverInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

/** Прави източник на знания (RAG) достъпен за агента като обикновен инструмент. */
final class RetrieverTool implements ToolInterface
{
    private const MAX_DOCUMENT_CHARS = 2000;

    public function __construct(
        private string $name,
        private string $description,
        private RetrieverInterface $retriever,
        private int $limit = 5,
    ) {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition($this->name, $this->description, [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500]],
            'required' => ['query'],
        ]);
    }

    public function execute(array $arguments): string
    {
        try {
            $documents = $this->retriever->retrieve((string) $arguments['query'], $this->limit);
        } catch (\Throwable) {
            throw new ToolException('Търсенето е недостъпно.');
        }

        if ($documents === []) {
            return 'Няма намерени резултати.';
        }

        $parts = [];
        foreach (array_slice($documents, 0, $this->limit) as $i => $document) {
            $parts[] = sprintf("[%d] %s\n%s", $i + 1, $document->source, mb_substr($document->content, 0, self::MAX_DOCUMENT_CHARS));
        }

        return implode("\n\n", $parts);
    }
}
