<?php

declare(strict_types=1);

namespace Ai\Design;

/** Обвива HTML за показване в iframe: Content-Security-Policy, която пуска само вградени стилове и картинки, и по избор адрес на сайта за вградения Bootstrap. */
final class PreviewDocument
{
    /**
     * @param ?string $origin произход на сайта (https://host), от който се зарежда вграденият Bootstrap; null = нищо външно
     * @param ?string $baseHref основен адрес за относителните пътища (завършва на /)
     */
    public static function wrap(string $html, ?string $origin = null, ?string $baseHref = null): string
    {
        $allowed = $origin === null ? '' : ' ' . $origin;
        $csp = "default-src 'none'; img-src data: https:{$allowed}; style-src 'unsafe-inline'{$allowed}; font-src data:{$allowed}";
        $tags = '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($csp, ENT_QUOTES) . '">'
            . ($baseHref === null ? '' : '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">');

        // (?:\s[^>]*)? не пуска <header>.
        if (preg_match('/<head(?:\s[^>]*)?>/i', $html) === 1) {
            return preg_replace('/<head(?:\s[^>]*)?>/i', '$0' . $tags, $html, 1) ?? $html;
        }
        if (preg_match('/<html(?:\s[^>]*)?>/i', $html) === 1) {
            return preg_replace('/<html(?:\s[^>]*)?>/i', '$0<head>' . $tags . '</head>', $html, 1) ?? $html;
        }

        return '<!DOCTYPE html><html><head>' . $tags . '</head><body>' . $html . '</body></html>';
    }
}
