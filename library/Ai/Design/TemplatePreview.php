<?php

declare(strict_types=1);

namespace Ai\Design;

/** Преглед на шаблон от пакет: стиловете и малките картинки се вграждат в самия документ, а скриптовете се махат. */
final class TemplatePreview
{
    private const IMAGE_BYTES = 150_000;
    private const TOTAL_BYTES = 2_500_000;
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

    /** @param \Closure(string): ?string $read съдържанието на файл от пакета по относителен път */
    public static function render(string $html, string $entry, \Closure $read): string
    {
        $dom = HtmlSanitizer::load($html);
        $xpath = new \DOMXPath($dom);
        $directory = str_contains($entry, '/') ? dirname($entry) : '';
        $budget = self::TOTAL_BYTES;

        foreach (iterator_to_array($xpath->query('//link[@rel="stylesheet"]') ?: []) as $link) {
            $path = self::localPath($directory, $link->getAttribute('href'));
            $css = $path === null ? null : $read($path);
            if ($css !== null) {
                $style = $dom->createElement('style');
                $style->appendChild($dom->createTextNode(self::inlineImages($css, dirname($path) === '.' ? '' : dirname($path), $read, $budget)));
                $link->parentNode?->insertBefore($style, $link);
            }
            $link->parentNode?->removeChild($link);
        }

        foreach (iterator_to_array($xpath->query('//img[@src]') ?: []) as $img) {
            $path = self::localPath($directory, $img->getAttribute('src'));
            $uri = $path === null ? null : self::dataUri($path, $read, $budget);
            $uri === null ? $img->removeAttribute('src') : $img->setAttribute('src', $uri);
        }

        return HtmlSanitizer::sanitize(HtmlSanitizer::save($dom));
    }

    /** Относителен път в рамките на пакета; null за външни адреси и за пътища, които излизат от пакета. */
    public static function localPath(string $directory, string $href): ?string
    {
        $href = trim(preg_replace('/[?#].*$/', '', $href) ?? '');
        if ($href === '' || str_starts_with($href, '//') || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $href) === 1) {
            return null;
        }

        $segments = $href[0] === '/' ? [] : ($directory === '' ? [] : explode('/', $directory));
        foreach (explode('/', ltrim($href, '/')) as $part) {
            if ($part === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);
            } elseif ($part !== '.' && $part !== '') {
                $segments[] = $part;
            }
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    private static function inlineImages(string $css, string $directory, \Closure $read, int &$budget): string
    {
        return preg_replace_callback('/url\(\s*([\'"]?)([^)\'"]+)\1\s*\)/i', static function (array $m) use ($directory, $read, &$budget): string {
            $path = self::localPath($directory, $m[2]);
            $uri = $path === null ? null : self::dataUri($path, $read, $budget);

            return $uri === null ? $m[0] : 'url(' . $uri . ')';
        }, $css) ?? $css;
    }

    private static function dataUri(string $path, \Closure $read, int &$budget): ?string
    {
        $mime = self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        if ($mime === null) {
            return null;
        }
        $content = $read($path);
        if ($content === null || strlen($content) > self::IMAGE_BYTES || strlen($content) > $budget) {
            return null;
        }
        $budget -= strlen($content);

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
