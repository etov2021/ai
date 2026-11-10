<?php

declare(strict_types=1);

namespace Ai\Storage;

use Ai\Exception\AiException;
use Ai\Registry\ProviderRegistry;
use Ai\Security\KeyVault;
use Ai\Security\Policy;

final class ProviderRepository
{
    private const TABLE = 'AiProviders';

    public function __construct(
        private DatabaseInterface $db,
        private KeyVault $vault,
        private Policy $policy,
        private ProviderRegistry $registry,
    ) {
    }

    /** @return list<ProviderConfig> */
    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM `AiProviders`' . ($onlyActive ? ' WHERE `active` = 1' : '') . ' ORDER BY `name`';

        return array_map(ProviderConfig::fromRow(...), $this->db->fetchAll($sql));
    }

    public function findByCode(string $code): ?ProviderConfig
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiProviders` WHERE `code` = ?', [$code]);

        return $row === null ? null : ProviderConfig::fromRow($row);
    }

    /** Разшифрованият ключ се връща само на вътрешния код, който прави заявката към доставчика. */
    public function apiKey(string $code): ?string
    {
        $row = $this->db->fetchRow('SELECT `apiKeyEnc` FROM `AiProviders` WHERE `code` = ?', [$code]);
        if ($row === null || empty($row['apiKeyEnc'])) {
            return null;
        }

        return $this->vault->decrypt((string) $row['apiKeyEnc'], $this->context($code));
    }

    /**
     * Създава или обновява по код. Празен $apiKey при обновяване запазва текущия ключ.
     * Кодът е непроменим: той е част от контекста на криптиране.
     */
    public function save(
        string $code,
        string $name,
        string $type,
        ?string $baseUrl,
        int $timeoutSeconds,
        bool $active,
        #[\SensitiveParameter] ?string $apiKey = null,
    ): ProviderConfig {
        $this->policy->assertCode($code);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително и до 100 знака.');
        }
        if (!$this->registry->has($type)) {
            throw new AiException(sprintf('Непознат тип доставчик "%s".', $type));
        }
        if ($timeoutSeconds < 1 || $timeoutSeconds > 600) {
            throw new AiException('Таймаутът трябва да е между 1 и 600 секунди.');
        }

        $baseUrl = $baseUrl === '' ? null : $baseUrl;
        $effectiveUrl = $baseUrl ?? $this->registry->defaultBaseUrl($type);
        if ($effectiveUrl !== null) {
            ($this->registry->isLocal($type) ? $this->policy->allowingPrivateHosts() : $this->policy)->assertBaseUrl($effectiveUrl);
        } elseif ($type === 'openai_compatible') {
            throw new AiException('Този тип доставчик изисква базов адрес.');
        }

        $existing = $this->findByCode($code);
        $apiKey = $apiKey === '' ? null : $apiKey;
        if ($apiKey === null && !($existing?->hasApiKey) && $this->registry->requiresKey($type)) {
            throw new AiException('API ключът е задължителен за този тип доставчик.');
        }

        $data = [
            'name' => $name,
            'type' => $type,
            'baseUrl' => $baseUrl,
            'timeoutSeconds' => $timeoutSeconds,
            'active' => $active ? 1 : 0,
            'updated' => date('Y-m-d H:i:s'),
        ];
        if ($apiKey !== null) {
            $data['apiKeyEnc'] = $this->vault->encrypt($apiKey, $this->context($code));
            $data['apiKeyHint'] = KeyVault::hint($apiKey);
        }

        if ($existing === null) {
            $data += ['code' => $code, 'created' => $data['updated']];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
        } else {
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'code'), [...array_values($data), $code]);
        }

        return $this->findByCode($code) ?? throw new AiException('Записът не може да бъде прочетен.');
    }

    public function clearApiKey(string $code): void
    {
        $this->db->execute('UPDATE `AiProviders` SET `apiKeyEnc` = NULL, `apiKeyHint` = NULL, `updated` = ? WHERE `code` = ?', [date('Y-m-d H:i:s'), $code]);
    }

    private function context(string $code): string
    {
        return 'provider:' . $code;
    }
}
