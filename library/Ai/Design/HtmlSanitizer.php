<?php

declare(strict_types=1);

namespace Ai\Design;

/**
 * Чисти HTML на модела, за да може да се копира без изненади: без скриптове, вградени обекти, формуляри към чужди адреси и външни ресурси.
 * Това е втора линия на защита; прегледът така или иначе е в iframe с sandbox без скриптове.
 */
final class HtmlSanitizer
{
    /** Bootstrap 5 за сайта: собствено копие в public/templates/frontend, независимо от админа. Единственият външен файл, който макет със стек Bootstrap може да включва. */
    public const BOOTSTRAP_CSS = 'templates/frontend/vendor/bootstrap/css/bootstrap.min.css';
    public const BOOTSTRAP_JS = 'templates/frontend/vendor/bootstrap/js/bootstrap.bundle.min.js';
    public const APP_STYLESHEETS = [self::BOOTSTRAP_CSS];

    // meta (charset, viewport) и form остават: макетите ги ползват. Махат се само meta refresh и адресите на формулярите.
    private const REMOVE = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'noscript', 'template', 'meta[@http-equiv]'];
    private const URL_ATTRIBUTES = ['href', 'src', 'xlink:href', 'poster', 'background', 'data'];

    public static function sanitize(string $html): string
    {
        $dom = self::load($html);
        $xpath = new \DOMXPath($dom);

        foreach (self::REMOVE as $tag) {
            foreach (iterator_to_array($xpath->query('//' . $tag) ?: []) as $element) {
                $element->parentNode?->removeChild($element);
            }
        }

        // Външни файлове не се допускат; изключение са стиловете на самото приложение.
        foreach (iterator_to_array($xpath->query('//link') ?: []) as $link) {
            if (strtolower($link->getAttribute('rel')) !== 'stylesheet' || !in_array($link->getAttribute('href'), self::APP_STYLESHEETS, true)) {
                $link->parentNode?->removeChild($link);
            }
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = $attribute->nodeValue ?? '';

                if (str_starts_with($name, 'on')
                    || $name === 'action' || $name === 'formaction'
                    || (in_array($name, self::URL_ATTRIBUTES, true) && !self::safeUrl($value))
                    || ($name === 'style' && !self::safeCss($value))
                    || $name === 'srcdoc'
                ) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }

        foreach (iterator_to_array($xpath->query('//style') ?: []) as $style) {
            $style->textContent = self::cleanCss($style->textContent);
        }

        return self::save($dom);
    }

    public static function load(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Префиксът казва на libxml, че текстът е UTF-8, иначе кирилицата се чупи.
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($dom->childNodes as $node) {
            if ($node instanceof \DOMProcessingInstruction) {
                $dom->removeChild($node);
                break;
            }
        }

        return $dom;
    }

    public static function save(\DOMDocument $dom): string
    {
        $out = trim((string) $dom->saveHTML());

        // libxml записва всеки не-ASCII знак като &#NNNN;, което прави кода за копиране нечетим. Само знаците над 127 се връщат: те нямат значение за разметката.
        $out = preg_replace_callback('/&#(\d{3,7});/', static fn (array $m): string => (int) $m[1] > 127 && (int) $m[1] <= 0x10FFFF ? (mb_chr((int) $m[1], 'UTF-8') ?: $m[0]) : $m[0], $out) ?? $out;

        // libxml добавя старо HTML 4 doctype към части от документ: заменя се със стандартното.
        $out = preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $out) ?? $out;

        return "<!DOCTYPE html>\n" . $out;
    }

    /** Разрешени са котви (#), относителни пътища, http(s), mailto, tel и data:image (без svg със скриптове се справя sandbox). */
    public static function safeUrl(string $value): bool
    {
        $value = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($value)) ?? '');

        return $value === '' || preg_match('~^(?:#|/|\.|\?|https?:|mailto:|tel:|data:image/(?:png|jpe?g|gif|webp|svg\+xml)[;,])~', $value) === 1
            || preg_match('~^[a-z0-9_\-./]+$~', $value) === 1;
    }

    private static function safeCss(string $css): bool
    {
        return preg_match('/expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding|url\s*\(\s*[\'"]?\s*(?!data:image|https?:)/i', $css) !== 1;
    }

    public static function cleanCss(string $css): string
    {
        // @import зарежда външни стилове; javascript: и expression() изпълняват код в стари браузъри.
        $css = preg_replace('/@import[^;]*;?/i', '', $css) ?? '';

        return preg_replace('/expression\s*\([^)]*\)|javascript:|behavior\s*:[^;]*;?|-moz-binding[^;]*;?/i', '', $css) ?? '';
    }
}
