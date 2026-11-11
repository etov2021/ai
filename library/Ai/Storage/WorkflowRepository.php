<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Exception\AiException;
use Ai\Security\Policy;
use Ai\Workflow\WorkflowDefinition;

final class WorkflowRepository
{
    private const TABLE = 'AiWorkflows';

    public function __construct(private DatabaseInterface $db, private Policy $policy, private AgentRepository $agents)
    {
    }

    /** @return list<WorkflowRecord> */
    public function all(): array
    {
        return array_map(WorkflowRecord::fromRow(...), $this->db->fetchAll('SELECT * FROM `AiWorkflows` ORDER BY `code`'));
    }

    public function findByCode(string $code): ?WorkflowRecord
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiWorkflows` WHERE `code` = ?', [$code]);

        return $row === null ? null : WorkflowRecord::fromRow($row);
    }

    /** @param array<string, mixed> $definition */
    public function save(string $code, string $name, ?string $description, array $definition, bool $active): WorkflowRecord
    {
        $this->policy->assertCode($code);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително и до 100 знака.');
        }
        if ($description !== null && mb_strlen($description) > 1000) {
            throw new AiException('Описанието е до 1000 знака.');
        }

        $parsed = WorkflowDefinition::fromArray($definition);
        foreach ($parsed->agentCodes() as $agentCode) {
            if ($this->agents->findByCode($agentCode) === null) {
                throw new AiException(sprintf('Непознат агент "%s".', $agentCode));
            }
        }

        $data = [
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'definition' => json_encode($parsed->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'active' => $active ? 1 : 0,
            'updated' => date('Y-m-d H:i:s'),
        ];

        if ($this->findByCode($code) === null) {
            $data += ['code' => $code, 'created' => $data['updated']];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
        } else {
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'code'), [...array_values($data), $code]);
        }

        return $this->findByCode($code) ?? throw new AiException('Записът не може да бъде прочетен.');
    }
}
