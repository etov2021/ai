<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Agent\Agent;
use Ai\Exception\AiException;
use Ai\Security\Policy;

final class AgentRepository
{
    private const TABLE = 'AiAgents';

    public function __construct(private DatabaseInterface $db, private Policy $policy)
    {
    }

    /** @return list<Agent> */
    public function all(): array
    {
        $agents = [];
        foreach ($this->db->fetchAll('SELECT `code` FROM `AiAgents` ORDER BY `name`') as $row) {
            $agents[] = $this->findByCode((string) $row['code']);
        }

        return array_values(array_filter($agents));
    }

    public function findByCode(string $code): ?Agent
    {
        $row = $this->db->fetchRow(
            'SELECT a.*, m.`model` AS modelName, p.`code` AS providerCode,
                    m.`inputPricePerMillion`, m.`outputPricePerMillion`,
                    (a.`active` = 1 AND m.`active` = 1 AND p.`active` = 1) AS runnable
             FROM `AiAgents` a
             INNER JOIN `AiModels` m ON m.`id` = a.`modelId`
             INNER JOIN `AiProviders` p ON p.`id` = m.`providerId`
             WHERE a.`code` = ?',
            [$code],
        );
        if ($row === null) {
            return null;
        }

        $tools = $this->db->fetchAll(
            'SELECT t.`code` FROM `AiAgentTools` x INNER JOIN `AiTools` t ON t.`id` = x.`toolId` WHERE x.`agentId` = ? ORDER BY t.`code`',
            [(int) $row['id']],
        );

        return (new Agent(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['systemPrompt'],
            (string) $row['providerCode'],
            (string) $row['modelName'],
            $row['temperature'] !== null ? (float) $row['temperature'] : null,
            $row['maxTokens'] !== null ? (int) $row['maxTokens'] : null,
            (int) $row['maxSteps'],
            (int) $row['timeoutSeconds'],
            (bool) $row['runnable'],
            array_map(static fn (array $t): string => (string) $t['code'], $tools),
            (float) $row['inputPricePerMillion'],
            (float) $row['outputPricePerMillion'],
            (int) $row['modelId'],
            (bool) $row['active'],
        ))->withEscalation($this->escalationFor($row));
    }

    /**
     * Ескалация само когато целевият модел и доставчик са активни; иначе агентът работи само с основния модел.
     *
     * @param array<string, mixed> $row
     */
    private function escalationFor(array $row): ?Agent
    {
        if (empty($row['escalationModelId'])) {
            return null;
        }

        $target = $this->db->fetchRow(
            'SELECT m.`id`, m.`model`, m.`inputPricePerMillion`, m.`outputPricePerMillion`, p.`code` AS providerCode
             FROM `AiModels` m INNER JOIN `AiProviders` p ON p.`id` = m.`providerId`
             WHERE m.`id` = ? AND m.`active` = 1 AND p.`active` = 1',
            [(int) $row['escalationModelId']],
        );
        if ($target === null) {
            return null;
        }

        // Създава се от основния агент (без инструментите в заявката, те се взимат по id на агента).
        return (new Agent(
            (int) $row['id'], (string) $row['code'], (string) $row['name'], (string) $row['systemPrompt'],
            (string) $target['providerCode'], (string) $target['model'],
            $row['temperature'] !== null ? (float) $row['temperature'] : null,
            $row['maxTokens'] !== null ? (int) $row['maxTokens'] : null,
            (int) $row['maxSteps'], (int) $row['timeoutSeconds'], (bool) $row['runnable'], [],
            (float) $target['inputPricePerMillion'], (float) $target['outputPricePerMillion'], (int) $target['id'], (bool) $row['active'],
        ));
    }

    /** @param list<string> $toolCodes */
    public function save(
        string $code,
        string $name,
        string $systemPrompt,
        int $modelId,
        array $toolCodes = [],
        ?float $temperature = null,
        ?int $maxTokens = null,
        int $maxSteps = 5,
        int $timeoutSeconds = 120,
        bool $active = true,
        ?string $description = null,
        ?int $escalationModelId = null,
    ): Agent {
        $this->policy->assertCode($code);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително и до 100 знака.');
        }
        if ($systemPrompt === '' || mb_strlen($systemPrompt) > 20000) {
            throw new AiException('Инструкциите са задължителни и до 20000 знака.');
        }
        if ($temperature !== null && ($temperature < 0 || $temperature > 2)) {
            throw new AiException('Температурата трябва да е между 0 и 2.');
        }
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new AiException('maxTokens трябва да е положително число.');
        }
        if ($maxSteps < 1 || $maxSteps > 20) {
            throw new AiException('Стъпките трябва да са между 1 и 20.');
        }
        if ($timeoutSeconds < 5 || $timeoutSeconds > 900) {
            throw new AiException('Времето за изпълнение трябва да е между 5 и 900 секунди.');
        }
        if ($this->db->fetchRow('SELECT `id` FROM `AiModels` WHERE `id` = ?', [$modelId]) === null) {
            throw new AiException('Моделът не съществува.');
        }
        if ($escalationModelId !== null) {
            if ($escalationModelId === $modelId) {
                throw new AiException('Моделът за ескалация трябва да е различен от основния.');
            }
            if ($this->db->fetchRow('SELECT `id` FROM `AiModels` WHERE `id` = ?', [$escalationModelId]) === null) {
                throw new AiException('Моделът за ескалация не съществува.');
            }
        }

        $toolIds = [];
        foreach (array_unique($toolCodes) as $toolCode) {
            $tool = $this->db->fetchRow('SELECT `id` FROM `AiTools` WHERE `code` = ?', [$toolCode]);
            if ($tool === null) {
                throw new AiException(sprintf('Инструментът "%s" не съществува.', $toolCode));
            }
            $toolIds[] = (int) $tool['id'];
        }

        $data = [
            'name' => $name,
            'systemPrompt' => $systemPrompt,
            'modelId' => $modelId,
            'escalationModelId' => $escalationModelId,
            'temperature' => $temperature,
            'maxTokens' => $maxTokens,
            'maxSteps' => $maxSteps,
            'timeoutSeconds' => $timeoutSeconds,
            'active' => $active ? 1 : 0,
            'updated' => date('Y-m-d H:i:s'),
        ];
        if ($description !== null) {
            $data['description'] = $description;
        }

        $existing = $this->db->fetchRow('SELECT `id` FROM `AiAgents` WHERE `code` = ?', [$code]);
        if ($existing === null) {
            $data += ['code' => $code, 'created' => $data['updated']];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
            $agentId = $this->db->lastInsertId();
        } else {
            $agentId = (int) $existing['id'];
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'code'), [...array_values($data), $code]);
        }

        // При прекъсване между двете стъпки агентът остава с по-малко инструменти, т.е. с по-малко права.
        $this->db->execute('DELETE FROM `AiAgentTools` WHERE `agentId` = ?', [$agentId]);
        foreach ($toolIds as $toolId) {
            $this->db->execute('INSERT INTO `AiAgentTools` (`agentId`, `toolId`) VALUES (?, ?)', [$agentId, $toolId]);
        }

        return $this->findByCode($code) ?? throw new AiException('Записът не може да бъде прочетен.');
    }
}
