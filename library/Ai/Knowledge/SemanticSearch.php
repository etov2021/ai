<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Extension\Document;

/** Търсене по смисъл: заявката се превръща във вектор и се сравнява с векторите на частите. */
final class SemanticSearch
{
    public function __construct(
        private EmbeddingRepository $embeddings,
        private GeminiEmbedder $embedder,
        // Под този праг частта се смята за несвързана; нужен е, защото най-близките по вектор винаги има, дори да няма подходящи.
        private float $minSimilarity = 0.0,
    ) {
    }

    /**
     * @param ?list<int> $documentIds null = всички документи
     * @return list<Document> празен списък, ако няма индекс или Gemini не отговаря (търсенето по ключови думи продължава да работи)
     */
    public function search(string $query, int $limit, ?array $documentIds = null): array
    {
        $model = $this->embedder->model();

        try {
            if (trim($query) === '' || !$this->embeddings->hasAny($model)) {
                return [];
            }
            $hits = $this->embeddings->nearest($this->embedder->embedQuery($query), $model, $limit, $documentIds);
        } catch (\Throwable) {
            // Липсваща таблица (миграция 013), липсващ ключ или недостъпен Gemini не бива да чупят търсенето по ключови думи.
            return [];
        }

        $hits = array_values(array_filter($hits, fn (array $h): bool => $h['similarity'] >= $this->minSimilarity));
        $chunks = $this->embeddings->chunks(array_column($hits, 'chunkId'));

        $documents = [];
        foreach ($hits as $hit) {
            if (isset($chunks[$hit['chunkId']])) {
                $c = $chunks[$hit['chunkId']];
                $documents[] = new Document($c['content'], sprintf('%s (част %d)', $c['title'], $c['position'] + 1), $hit['similarity']);
            }
        }

        return $documents;
    }
}
