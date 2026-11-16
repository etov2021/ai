<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Contract\RetrieverInterface;

/** Търсене в библиотеката с документи. Резултатите са най-подходящите части, първо най-добрите. */
final class DocumentRetriever implements RetrieverInterface
{
    private const MAX_TERMS = 12;

    public function __construct(private DocumentRepository $documents, private ?array $documentIds = null)
    {
    }

    /** @param ?list<int> $documentIds null = всички документи */
    public function scopedTo(?array $documentIds): self
    {
        return new self($this->documents, $documentIds);
    }

    public function retrieve(string $query, int $limit): array
    {
        $boolean = self::booleanQuery($query);

        return $boolean === '' ? [] : $this->documents->search($boolean, $limit, $this->documentIds);
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
