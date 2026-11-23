<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;

/** Добавя шаблон в библиотеката от поставен HTML, качен файл (.html или .zip пакет) или адрес на страница. */
final class TemplateImporter
{
    private const MAX_HTML_BYTES = 2_000_000;

    public function __construct(private TemplateRepository $templates, private PackageImporter $packages, private ReferenceFetcher $fetcher)
    {
    }

    public function fromHtml(string $name, string $tags, string $html, ?int $userId): int
    {
        $html = trim($html);
        if ($html === '' || strlen($html) > self::MAX_HTML_BYTES) {
            throw new AiException('HTML кодът е празен или по-голям от 2 MB.');
        }

        return $this->templates->create($name, $tags, 'paste', null, null, $html, $this->fetcher->summarizeHtml($html), false, $userId);
    }

    /** @throws AiException */
    public function fromFile(string $name, string $tags, string $path, string $originalName, ?int $userId): int
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['html', 'htm'], true)) {
            $size = (int) @filesize($path);
            if ($size < 1 || $size > self::MAX_HTML_BYTES) {
                throw new AiException('HTML файлът е празен или по-голям от 2 MB.');
            }

            return $this->fromHtml($name, $tags, ReferenceFetcher::utf8((string) file_get_contents($path)), $userId);
        }
        if ($extension !== 'zip') {
            throw new AiException('Поддържат се .html и .zip файлове.');
        }

        // Записът се създава първо, за да има папка за пакета; при грешка се изтрива заедно с файловете.
        $id = $this->templates->create($name, $tags, 'zip', null, null, null, '', true, $userId);

        try {
            $result = $this->packages->import($path, $this->templates->packageDir($id));
            $html = ReferenceFetcher::utf8((string) $this->templates->read($id, $result['entry']));
            $directory = str_contains($result['entry'], '/') ? dirname($result['entry']) : '';

            $summary = $this->fetcher->summarizeHtml($html, function (string $href) use ($id, $directory): ?string {
                $local = TemplatePreview::localPath($directory, $href);
                $css = $local === null ? null : $this->templates->read($id, $local);

                return $css === null ? null : ReferenceFetcher::utf8($css);
            });

            $this->templates->finalize($id, $result['entry'], $html, $summary);
        } catch (\Throwable $e) {
            $this->templates->delete($id);
            throw $e;
        }

        return $id;
    }

    /** @throws AiException */
    public function fromUrl(string $name, string $tags, string $url, ?int $userId): int
    {
        // Сваля се само описанието (структура и стил): файловете на чужд сайт не се копират.
        return $this->templates->create($name, $tags, 'url', $url, null, null, $this->fetcher->summarize($url), false, $userId);
    }
}
