<?php

declare(strict_types=1);

namespace Ai\Tool;

use Ai\Contract\HttpClientInterface;
use Ai\Contract\ToolInterface;
use Ai\Exception\AiException;
use Ai\Message\ToolDefinition;
use Ai\Security\Policy;
use Ai\Storage\ToolRecord;

/** Превръща записи от AiTools в изпълними инструменти. Вградените се регистрират от кода, без промяна тук. */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $builtin = [];

    public function __construct(private HttpClientInterface $http, private Policy $policy)
    {
    }

    public function registerBuiltin(ToolInterface $tool): void
    {
        $this->builtin[$tool->definition()->name] = $tool;
    }

    /**
     * Неактивните инструменти се пропускат, така че админът да може да изключи инструмент без да редактира агентите.
     *
     * @param list<ToolRecord> $records
     * @return list<ToolInterface>
     */
    public function resolve(array $records): array
    {
        $tools = [];
        foreach ($records as $record) {
            if (!$record->active) {
                continue;
            }
            $tools[] = match ($record->type) {
                'builtin' => $this->builtin[$record->code] ?? throw new AiException(sprintf('Вграденият инструмент "%s" не е регистриран в кода.', $record->code)),
                'http' => new HttpTool(
                    $this->http,
                    $this->policy,
                    new ToolDefinition($record->code, $record->description, $record->parameters),
                    $record->config,
                ),
                default => throw new AiException(sprintf('Непознат тип инструмент "%s".', $record->type)),
            };
        }

        return $tools;
    }
}
