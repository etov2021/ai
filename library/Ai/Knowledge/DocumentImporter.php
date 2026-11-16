<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\File\FileTextExtractor;

/** Извлича текста от файл и го добавя в библиотеката. */
final class DocumentImporter
{
    public function __construct(private FileTextExtractor $extractor, private DocumentRepository $documents)
    {
    }

    /** @return array{id: int, title: string, chars: int} */
    public function import(string $path, string $originalName, ?int $userId = null): array
    {
        $text = $this->extractor->extract($path, $originalName);

        // Името е само за показване и разширение; на диска файлът е с генерирано име.
        $clean = trim(preg_replace('/[\p{C}]+/u', ' ', $originalName) ?? '');
        $title = pathinfo($clean, PATHINFO_FILENAME) ?: 'Документ';
        $id = $this->documents->add($title, $clean, strtolower(pathinfo($clean, PATHINFO_EXTENSION)), (int) @filesize($path), $text, $userId);

        return ['id' => $id, 'title' => $title, 'chars' => mb_strlen($text)];
    }
}
