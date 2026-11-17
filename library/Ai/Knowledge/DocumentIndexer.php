<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Exception\AiException;

/** Създава вектори за частите, които още нямат такива (или са с друг модел). */
final class DocumentIndexer
{
    public function __construct(private EmbeddingRepository $embeddings, private GeminiEmbedder $embedder)
    {
    }

    /**
     * @return array{indexed: int, remaining: int, error: ?string} при грешка (напр. лимит на Gemini) спира; следващо пускане продължава оттам
     */
    public function run(int $maxChunks = 500): array
    {
        $model = $this->embedder->model();
        $indexed = 0;
        $error = null;

        try {
            while ($indexed < $maxChunks) {
                $batch = $this->embeddings->pending($model, min(GeminiEmbedder::BATCH, $maxChunks - $indexed));
                if ($batch === []) {
                    break;
                }

                $vectors = $this->embedder->embedDocuments(array_column($batch, 'content'));
                $this->embeddings->store($model, array_combine(array_column($batch, 'id'), $vectors));
                $indexed += count($batch);
            }
        } catch (AiException $e) {
            $error = $e->getMessage();
        }

        $status = $this->embeddings->status($model);

        return ['indexed' => $indexed, 'remaining' => max(0, $status['total'] - $status['indexed']), 'error' => $error];
    }
}
