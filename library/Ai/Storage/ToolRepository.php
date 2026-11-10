<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Exception\AiException;
use Ai\Security\Policy;
use Ai\Tool\HttpTool;

final class ToolRepository
{
    public const TYPES = ['builtin', 'http'];
    private const TABLE = 'AiTools';

    public function __construct(private DatabaseInterface $db, private Policy $policy)
    {
    }

    /** @return list<ToolRecord> */
    public function all(): array
    {
        return array_map(ToolRecord::fromRow(...), $this->db->fetchAll('SELECT * FROM `AiTools` ORDER BY `code`'));
    }

    public function findByCode(string $code): ?ToolRecord
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiTools` WHERE `code` = ?', [$code]);

        return $row === null ? null : ToolRecord::fromRow($row);
    }

    /** @return list<ToolRecord> инструментите, разрешени за агента (по таблицата AiAgentTools) */
    public function forAgent(int $agentId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.* FROM `AiTools` t INNER JOIN `AiAgentTools` x ON x.`toolId` = t.`id` WHERE x.`agentId` = ? ORDER BY t.`code`',
            [$agentId],
        );

        return array_map(ToolRecord::fromRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $parameters JSON Schema от тип object
     * @param array<string, mixed> $config
     */
    public function save(string $code, string $name, string $description, string $type, array $parameters, array $config, bool $active): ToolRecord
    {
        $this->policy->assertCode($code);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително и до 100 знака.');
        }
        if ($description === '' || mb_strlen($description) > 1000) {
            throw new AiException('Описанието е задължително и до 1000 знака: то се подава на модела.');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new AiException('Непознат тип инструмент.');
        }
        if (($parameters['type'] ?? null) !== 'object') {
            throw new AiException('Параметрите трябва да са JSON Schema от тип object.');
        }
        $parametersJson = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($parametersJson === false || strlen($parametersJson) > 20000) {
            throw new AiException('Схемата на параметрите е невалидна или прекалено голяма.');
        }
        if ($type === 'http') {
            HttpTool::assertConfig($config);
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'parameters' => $parametersJson,
            'config' => $config === [] ? null : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
