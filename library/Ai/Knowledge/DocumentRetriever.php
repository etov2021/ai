<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Contract\RetrieverInterface;
use Ai\Extension\Document;

/** Търсене в библиотеката с документи: по ключови думи (FULLTEXT) и, ако има индекс, по смисъл (вектори). Първо най-добрите части. */
final class DocumentRetriever implements RetrieverInterface
{
    private const MAX_TERMS = 12;
    // Стандартна константа на reciprocal rank fusion: омекотява разликата между най-горните места.
    private const RRF_K = 60;

    public function __construct(private DocumentRepository $documents, private ?array $documentIds = null, private ?SemanticSearch $semantic = null)
    {
    }

    /** @param ?list<int> $documentIds null = всички документи */
    public function scopedTo(?array $documentIds): self
    {
        return new self($this->documents, $documentIds, $this->semantic);
    }

    public function retrieve(string $query, int $limit): array
    {
        $boolean = self::booleanQuery($query);
        $semantic = $this->semantic?->search($query, $limit * 2, $this->documentIds) ?? [];

        if ($semantic === []) {
            return $boolean === '' ? [] : $this->documents->search($boolean, $limit, $this->documentIds);
        }

        $lexical = $boolean === '' ? [] : $this->documents->search($boolean, $limit * 2, $this->documentIds);

        return self::fuse($lexical, $semantic, $limit);
    }

    /**
     * Обединява двата класирания по място, а не по оценка: оценките на FULLTEXT и на косинусовото сходство не са съпоставими.
     *
     * @param list<Document> $lexical
     * @param list<Document> $semantic
     * @return list<Document> оценката е нормирана до 1 за част, която е първа и в двата списъка
     */
    public static function fuse(array $lexical, array $semantic, int $limit): array
    {
        $scores = [];
        $documents = [];
        foreach ([$lexical, $semantic] as $list) {
            foreach ($list as $rank => $doc) {
                $documents[$doc->source] ??= $doc;
                $scores[$doc->source] = ($scores[$doc->source] ?? 0.0) + 1 / (self::RRF_K + $rank + 1);
            }
        }
        arsort($scores);

        $max = 2 / (self::RRF_K + 1);
        $result = [];
        foreach (array_slice($scores, 0, $limit, true) as $source => $score) {
            $result[] = new Document($documents[$source]->content, (string) $source, $score / $max);
        }

        return $result;
    }

    /** Превръща свободен текст в заявка с представки, напр. "договорите" -> "договор*". */
    public static function booleanQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($query), $matches);

        $terms = [];
        foreach (array_slice(array_unique($matches[0]), 0, self::MAX_TERMS) as $word) {
            // Български думи се променят по окончание: търси се по началото им. Само букви и цифри влизат в заявката, затова операторите на MATCH не могат да се подадат отвън.
            $length = mb_strlen($word);
            $keep = $length >= 8 ? $length - 3 : ($length >= 6 ? $length - 2 : ($length >= 5 ? $length - 1 : $length));
            $terms[] = mb_substr($word, 0, $keep) . '*';
        }

        return implode(' ', $terms);
    }
}
