<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Agent\Agent;
use Ai\Contract\AgentAwareToolInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

/** Списък на документите, до които агентът има достъп (id, заглавие, брой части). */
final class DocsListTool implements AgentAwareToolInterface
{
    public const NAME = 'docs_list';

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
        return new ToolDefinition(
            self::NAME,
            'Връща списък с наличните документи: id, заглавие, брой части и знаци. Ползвай, за да узнаеш какво има и кой id да подадеш на docs_read.',
            ['type' => 'object', 'properties' => []],
        );
    }

    public function execute(array $arguments): string
    {
        try {
            $rows = $this->documents->listFor($this->documentIds);
        } catch (\Throwable) {
            throw new ToolException('Библиотеката с документи е недостъпна.');
        }
        if ($rows === []) {
            return 'Няма налични документи.';
        }

        $lines = [];
        foreach ($rows as $r) {
            $lines[] = sprintf('id=%d | %s | части: %d | знаци: %d', (int) $r['id'], preg_replace('/[\p{C}]+/u', ' ', (string) $r['title']), (int) $r['chunkCount'], (int) $r['chars']);
        }

        return implode("\n", $lines);
    }
}
