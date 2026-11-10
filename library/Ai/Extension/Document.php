<?php

declare(strict_types=1);

namespace Ai\Extension;

/** Фрагмент от база знания, върнат от RetrieverInterface (RAG). */
final readonly class Document
{
    public function __construct(public string $content, public string $source = '', public float $score = 0.0)
    {
    }
}
