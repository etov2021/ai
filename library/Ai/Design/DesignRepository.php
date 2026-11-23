<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Макетите и техните версии. */
final class DesignRepository
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT d.`id`, d.`title`, d.`created`, (SELECT MAX(v.`version`) FROM `AiUiDesignVersions` v WHERE v.`designId` = d.`id`) AS versions'
            . ' FROM `AiUiDesigns` d ORDER BY d.`id` DESC LIMIT 200',
        );
    }

    /** @return ?array{id: int, title: string, brief: DesignBrief, created: string} */
    public function find(int $id): ?array
    {
        $row = $this->db->fetchRow('SELECT `id`, `title`, `params`, `created` FROM `AiUiDesigns` WHERE `id` = ?', [$id]);
        if ($row === null) {
            return null;
        }

        return ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'brief' => DesignBrief::fromJson((string) $row['params']), 'created' => (string) $row['created']];
    }

    public function create(DesignBrief $brief, ?int $userId): int
    {
        $data = ['title' => $brief->title, 'params' => $brief->toJson(), 'userId' => $userId, 'created' => date('Y-m-d H:i:s')];
        $this->db->execute(Sql::insert('AiUiDesigns', array_keys($data)), array_values($data));

        return $this->db->lastInsertId();
    }

    /** @return int номерът на новата версия */
    public function addVersion(int $designId, string $instruction, string $html, string $agentCode): int
    {
        $row = $this->db->fetchRow('SELECT MAX(`version`) AS v FROM `AiUiDesignVersions` WHERE `designId` = ?', [$designId]);
        $version = (int) ($row['v'] ?? 0) + 1;

        $data = ['designId' => $designId, 'version' => $version, 'instruction' => mb_substr($instruction, 0, 4000), 'html' => $html, 'agentCode' => $agentCode, 'created' => date('Y-m-d H:i:s')];
        try {
            $this->db->execute(Sql::insert('AiUiDesignVersions', array_keys($data)), array_values($data));
        } catch (\Throwable $e) {
            // Две едновременни промени получават един и същи номер: втората се отхвърля от уникалния ключ.
            throw new AiException('Макетът е променен междувременно. Опитайте пак.', 0, $e);
        }

        return $version;
    }

    /** @return list<array{version: int, instruction: string, created: string}> без самия HTML */
    public function versions(int $designId): array
    {
        $rows = $this->db->fetchAll('SELECT `version`, `instruction`, `created` FROM `AiUiDesignVersions` WHERE `designId` = ? ORDER BY `version` DESC', [$designId]);

        return array_map(static fn (array $r): array => ['version' => (int) $r['version'], 'instruction' => (string) $r['instruction'], 'created' => (string) $r['created']], $rows);
    }

    /** @return ?array{version: int, html: string, instruction: string} */
    public function version(int $designId, int $version): ?array
    {
        $row = $this->db->fetchRow('SELECT `version`, `html`, `instruction` FROM `AiUiDesignVersions` WHERE `designId` = ? AND `version` = ?', [$designId, $version]);

        return $row === null ? null : ['version' => (int) $row['version'], 'html' => (string) $row['html'], 'instruction' => (string) $row['instruction']];
    }

    public function latestVersion(int $designId): ?int
    {
        $row = $this->db->fetchRow('SELECT MAX(`version`) AS v FROM `AiUiDesignVersions` WHERE `designId` = ?', [$designId]);

        return isset($row['v']) ? (int) $row['v'] : null;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiUiDesigns` WHERE `id` = ?', [$id]);
    }
}
