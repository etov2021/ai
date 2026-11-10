<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Extension\Document;

/** Източник на знания за RAG: търсене по текст във вектори, MySQL FULLTEXT, външна услуга и др. */
interface RetrieverInterface
{
    /** @return list<Document> най-подходящите първи */
    public function retrieve(string $query, int $limit): array;
}
