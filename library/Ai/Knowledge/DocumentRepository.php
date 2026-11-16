<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Exception\AiException;
use Ai\Extension\Document;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Документите и техните части за търсене (MySQL FULLTEXT). Оригиналните файлове не се пазят. */
final class DocumentRepository
{
    public const MAX_CHARS = 1_000_000;
    private const BATCH = 100;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT `id`, `title`, `fileName`, `extension`, `size`, `chars`, `chunkCount`, `created` FROM `AiDocuments` ORDER BY `created` DESC, `id` DESC');
    }

    /**
     * @return int id на новия документ
     * @throws AiException при дублиран или прекалено голям документ
     */
    public function add(string $title, string $fileName, string $extension, int $size, string $text, ?int $userId = null): int
    {
        $chars = mb_strlen($text);
        if ($chars > self::MAX_CHARS) {
            throw new AiException(sprintf('Документът е твърде голям (над %s знака).', number_format(self::MAX_CHARS, 0, '', ' ')));
        }

        // Хешът на текста спира повторното добавяне на същия документ, дори под друго име.
        $hash = sha1($text);
        $existing = $this->db->fetchRow('SELECT `title` FROM `AiDocuments` WHERE `hash` = ?', [$hash]);
        if ($existing !== null) {
            throw new AiException(sprintf('Документът вече е добавен (като "%s").', (string) $existing['title']));
        }

        $chunks = TextChunker::split($text);
        if ($chunks === []) {
            throw new AiException('Във файла не е намерен текст.');
        }

        $data = [
            'title' => mb_substr($title, 0, 255),
            'fileName' => mb_substr($fileName, 0, 255),
            'extension' => mb_substr($extension, 0, 10),
            'size' => max(0, $size),
            'chars' => $chars,
            'chunkCount' => count($chunks),
            'hash' => $hash,
            'userId' => $userId,
            'created' => date('Y-m-d H:i:s'),
        ];
        $this->db->execute(Sql::insert('AiDocuments', array_keys($data)), array_values($data));
        $id = $this->db->lastInsertId();

        try {
            foreach (array_chunk($chunks, self::BATCH, true) as $batch) {
                $params = [];
                foreach ($batch as $position => $content) {
                    array_push($params, $id, $position, $content);
                }
                $this->db->execute(
                    'INSERT INTO `AiDocumentChunks` (`documentId`, `position`, `content`) VALUES ' . implode(',', array_fill(0, count($batch), '(?,?,?)')),
                    $params,
                );
            }
        } catch (\Throwable $e) {
            // Без транзакция: непълният документ се премахва (частите се трият каскадно).
            $this->delete($id);
            throw $e instanceof AiException ? $e : new AiException('Документът не може да бъде записан.', 0, $e);
        }

        return $id;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiDocuments` WHERE `id` = ?', [$id]);
    }

    /** @return ?array<string, mixed> */
    public function find(int $id): ?array
    {
        return $this->db->fetchRow('SELECT `id`, `title`, `chars`, `chunkCount` FROM `AiDocuments` WHERE `id` = ?', [$id]);
    }

    /**
     * @param ?list<int> $ids null = всички документи
     * @return list<array<string, mixed>>
     */
    public function listFor(?array $ids, int $limit = 100): array
    {
        $where = $ids === null ? '' : ($ids === [] ? ' WHERE 1 = 0' : ' WHERE `id` IN (' . self::placeholders($ids) . ')');

        return $this->db->fetchAll(
            'SELECT `id`, `title`, `chars`, `chunkCount` FROM `AiDocuments`' . $where . ' ORDER BY `title` LIMIT ' . max(1, min($limit, 200)),
            $ids ?? [],
        );
    }

    /** @return list<array{position: int, content: string}> частите на документа по ред, от позиция $from (от 0) */
    public function parts(int $documentId, int $from, int $count): array
    {
        $rows = $this->db->fetchAll(
            'SELECT `position`, `content` FROM `AiDocumentChunks` WHERE `documentId` = ? AND `position` >= ? ORDER BY `position` LIMIT ' . max(1, min($count, 20)),
            [$documentId, max(0, $from)],
        );

        return array_map(static fn (array $r): array => ['position' => (int) $r['position'], 'content' => (string) $r['content']], $rows);
    }

    /** @return ?list<int> null, ако агентът няма ограничение (вижда всички документи) */
    public function idsForAgent(int $agentId): ?array
    {
        $rows = $this->db->fetchAll('SELECT `documentId` FROM `AiAgentDocuments` WHERE `agentId` = ?', [$agentId]);

        return $rows === [] ? null : array_map(static fn (array $r): int => (int) $r['documentId'], $rows);
    }

    /** @param list<int> $documentIds празен списък = без ограничение */
    public function setAgentDocuments(int $agentId, array $documentIds): void
    {
        $this->db->execute('DELETE FROM `AiAgentDocuments` WHERE `agentId` = ?', [$agentId]);

        $documentIds = array_values(array_unique(array_filter($documentIds, static fn ($id): bool => is_int($id) && $id > 0)));
        if ($documentIds === []) {
            return;
        }

        // Само съществуващи документи: изтрит междувременно не би дал грешка от външния ключ.
        $existing = $this->db->fetchAll('SELECT `id` FROM `AiDocuments` WHERE `id` IN (' . self::placeholders($documentIds) . ')', $documentIds);
        foreach ($existing as $row) {
            $this->db->execute('INSERT INTO `AiAgentDocuments` (`agentId`, `documentId`) VALUES (?, ?)', [$agentId, (int) $row['id']]);
        }
    }

    /** @param list<int> $ids */
    private static function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /**
     * @param string $booleanQuery заявка за MATCH ... IN BOOLEAN MODE
     * @param ?list<int> $documentIds само тези документи; null = всички
     * @return list<Document>
     */
    public function search(string $booleanQuery, int $limit, ?array $documentIds = null): array
    {
        if ($documentIds === []) {
            return [];
        }
        $scope = $documentIds === null ? '' : ' AND c.`documentId` IN (' . self::placeholders($documentIds) . ')';

        $rows = $this->db->fetchAll(
            'SELECT d.`title`, c.`position`, c.`content`, MATCH(c.`content`) AGAINST (? IN BOOLEAN MODE) AS score'
            . ' FROM `AiDocumentChunks` c INNER JOIN `AiDocuments` d ON d.`id` = c.`documentId`'
            . ' WHERE MATCH(c.`content`) AGAINST (? IN BOOLEAN MODE)' . $scope . ' ORDER BY score DESC LIMIT ' . max(1, min($limit, 50)),
            [$booleanQuery, $booleanQuery, ...($documentIds ?? [])],
        );

        return array_map(
            static fn (array $r): Document => new Document((string) $r['content'], sprintf('%s (част %d)', $r['title'], (int) $r['position'] + 1), (float) $r['score']),
            $rows,
        );
    }
}
