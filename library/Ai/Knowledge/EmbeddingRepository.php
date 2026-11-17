<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Storage\DatabaseInterface;

/** Векторите на частите на документите (таблица AiDocumentEmbeddings). */
final class EmbeddingRepository
{
    private const PAGE = 1000;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return list<array{id: int, content: string}> части без вектор от този модел */
    public function pending(string $model, int $limit): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.`id`, c.`content` FROM `AiDocumentChunks` c LEFT JOIN `AiDocumentEmbeddings` e ON e.`chunkId` = c.`id`'
            . ' WHERE e.`chunkId` IS NULL OR e.`model` <> ? ORDER BY c.`id` LIMIT ' . max(1, min($limit, 500)),
            [$model],
        );

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'content' => (string) $r['content']], $rows);
    }

    /** @return array{indexed: int, total: int} */
    public function status(string $model): array
    {
        $total = (int) ($this->db->fetchRow('SELECT COUNT(*) AS n FROM `AiDocumentChunks`')['n'] ?? 0);
        $indexed = (int) ($this->db->fetchRow('SELECT COUNT(*) AS n FROM `AiDocumentEmbeddings` WHERE `model` = ?', [$model])['n'] ?? 0);

        return ['indexed' => $indexed, 'total' => $total];
    }

    public function hasAny(string $model): bool
    {
        return $this->db->fetchRow('SELECT 1 AS x FROM `AiDocumentEmbeddings` WHERE `model` = ? LIMIT 1', [$model]) !== null;
    }

    /** @param array<int, list<float>> $vectors chunkId => вектор */
    public function store(string $model, array $vectors): void
    {
        foreach (array_chunk($vectors, 50, true) as $batch) {
            $params = [];
            foreach ($batch as $chunkId => $vector) {
                array_push($params, $chunkId, $model, self::encode($vector));
            }
            $this->db->execute(
                'REPLACE INTO `AiDocumentEmbeddings` (`chunkId`, `model`, `vector`) VALUES ' . implode(',', array_fill(0, count($batch), '(?,?,?)')),
                $params,
            );
        }
    }

    /**
     * Сравнява със всички вектори на модела на страници, без да ги държи наведнъж в паметта.
     * Скаларното произведение е косинусово сходство, защото векторите са нормализирани.
     *
     * @param list<float> $query
     * @param ?list<int> $documentIds null = всички документи
     * @return list<array{chunkId: int, similarity: float}> най-доброто първо
     */
    public function nearest(array $query, string $model, int $limit, ?array $documentIds = null): array
    {
        if ($documentIds === []) {
            return [];
        }
        $join = $documentIds === null ? '' : ' INNER JOIN `AiDocumentChunks` c ON c.`id` = e.`chunkId`';
        $scope = $documentIds === null ? '' : ' AND c.`documentId` IN (' . implode(',', array_fill(0, count($documentIds), '?')) . ')';

        $top = [];
        $after = 0;
        do {
            $rows = $this->db->fetchAll(
                'SELECT e.`chunkId`, e.`vector` FROM `AiDocumentEmbeddings` e' . $join
                . ' WHERE e.`model` = ? AND e.`chunkId` > ?' . $scope . ' ORDER BY e.`chunkId` LIMIT ' . self::PAGE,
                [$model, $after, ...($documentIds ?? [])],
            );

            foreach ($rows as $row) {
                $after = (int) $row['chunkId'];
                $vector = self::decode((string) $row['vector']);
                // Вектор с друга размерност (смяна на настройката) не е сравним.
                if (count($vector) !== count($query)) {
                    continue;
                }
                $dot = 0.0;
                foreach ($query as $i => $q) {
                    $dot += $q * $vector[$i];
                }
                $top[] = ['chunkId' => $after, 'similarity' => $dot];
            }

            usort($top, static fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);
            $top = array_slice($top, 0, $limit);
        } while (count($rows) === self::PAGE);

        return $top;
    }

    /**
     * @param list<int> $chunkIds
     * @return array<int, array{title: string, position: int, content: string}> по chunkId
     */
    public function chunks(array $chunkIds): array
    {
        if ($chunkIds === []) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT c.`id`, d.`title`, c.`position`, c.`content` FROM `AiDocumentChunks` c INNER JOIN `AiDocuments` d ON d.`id` = c.`documentId`'
            . ' WHERE c.`id` IN (' . implode(',', array_fill(0, count($chunkIds), '?')) . ')',
            $chunkIds,
        );

        $result = [];
        foreach ($rows as $r) {
            $result[(int) $r['id']] = ['title' => (string) $r['title'], 'position' => (int) $r['position'], 'content' => (string) $r['content']];
        }

        return $result;
    }

    /** @param list<float> $vector */
    public static function encode(array $vector): string
    {
        return base64_encode(pack('g*', ...$vector));
    }

    /** @return list<float> */
    public static function decode(string $encoded): array
    {
        $binary = base64_decode($encoded, true);
        $values = $binary === false || $binary === '' ? false : unpack('g*', $binary);

        return $values === false ? [] : array_values($values);
    }
}
