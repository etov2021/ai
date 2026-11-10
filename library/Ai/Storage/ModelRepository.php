<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Exception\AiException;
use Ai\Security\Policy;

final class ModelRepository
{
    private const TABLE = 'AiModels';

    public function __construct(private DatabaseInterface $db, private Policy $policy)
    {
    }

    /** @return list<ModelConfig> */
    public function forProvider(int $providerId, bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM `AiModels` WHERE `providerId` = ?' . ($onlyActive ? ' AND `active` = 1' : '') . ' ORDER BY `label`';

        return array_map(ModelConfig::fromRow(...), $this->db->fetchAll($sql, [$providerId]));
    }

    /** @return array<int, string> id на модел => "Доставчик / Модел" за падащи списъци */
    public function options(): array
    {
        $options = [];
        $rows = $this->db->fetchAll(
            'SELECT m.`id`, m.`label`, p.`name` AS providerName FROM `AiModels` m INNER JOIN `AiProviders` p ON p.`id` = m.`providerId` WHERE m.`active` = 1 AND p.`active` = 1 ORDER BY p.`name`, m.`label`',
        );
        foreach ($rows as $row) {
            $options[(int) $row['id']] = $row['providerName'] . ' / ' . $row['label'];
        }

        return $options;
    }

    public function find(int $providerId, string $model): ?ModelConfig
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiModels` WHERE `providerId` = ? AND `model` = ?', [$providerId, $model]);

        return $row === null ? null : ModelConfig::fromRow($row);
    }

    /** Цените са в EUR за 1 милион токена и се въвеждат от админа. */
    public function save(
        int $providerId,
        string $model,
        string $label,
        float $inputPricePerMillion,
        float $outputPricePerMillion,
        ?int $contextWindow = null,
        ?int $maxOutputTokens = null,
        bool $supportsTools = true,
        bool $active = true,
    ): ModelConfig {
        $this->policy->assertModelId($model);
        if ($label === '' || mb_strlen($label) > 100) {
            throw new AiException('Етикетът е задължителен и до 100 знака.');
        }
        foreach ([$inputPricePerMillion, $outputPricePerMillion] as $price) {
            if ($price < 0 || $price > 100000) {
                throw new AiException('Цената трябва да е между 0 и 100000.');
            }
        }
        foreach ([$contextWindow, $maxOutputTokens] as $limit) {
            if ($limit !== null && $limit < 1) {
                throw new AiException('Лимитите трябва да са положителни числа.');
            }
        }
        if ($this->db->fetchRow('SELECT `id` FROM `AiProviders` WHERE `id` = ?', [$providerId]) === null) {
            throw new AiException('Доставчикът не съществува.');
        }

        $data = [
            'label' => $label,
            'inputPricePerMillion' => $inputPricePerMillion,
            'outputPricePerMillion' => $outputPricePerMillion,
            'contextWindow' => $contextWindow,
            'maxOutputTokens' => $maxOutputTokens,
            'supportsTools' => $supportsTools ? 1 : 0,
            'active' => $active ? 1 : 0,
            'updated' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->find($providerId, $model);
        if ($existing === null) {
            $data += ['providerId' => $providerId, 'model' => $model, 'created' => $data['updated']];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
        } else {
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'id'), [...array_values($data), $existing->id]);
        }

        return $this->find($providerId, $model) ?? throw new AiException('Записът не може да бъде прочетен.');
    }
}
