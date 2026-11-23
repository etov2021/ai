<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;

/**
 * Превръща HTML документ (макет или шаблон) в layout за сайта: рамката се запазва, а областта за съдържание и секциите (заглавие, стилове, скриптове) стават места за изгледите.
 *
 * Сигурност: PHP кодът във файла идва само от фиксираните откъси в SNIPPETS. Всичко от шаблона и модела се чисти от <? преди и след обработката,
 * а inline скриптовете, външните скриптове и обработчиците на събития се махат (и се отбелязват в доклада).
 */
final class LayoutBuilder
{
    public const MARK = 'aiimpl-ui-layout';

    private const PLACEHOLDERS = ['@@AIIMPL_BASE@@', '@@AIIMPL_TITLE@@', '@@AIIMPL_STYLES@@', '@@AIIMPL_CONTENT@@', '@@AIIMPL_SCRIPTS@@'];
    private const SNIPPETS = [
        '<base href="<?php echo server_url(base_url()) . \'/\'; ?>" />',
        '<?php echo $this->renderSection(\'title\', (isset($this->metaRoute) && isset($this->metaRoute[\'title\']) ? $this->metaRoute[\'title\'] : \'\')); ?>',
        '<?php echo $this->renderSection(\'styles\'); ?>',
        '<?php echo $this->renderSection(\'content\'); ?>',
        '<?php echo $this->renderSection(\'scripts\'); ?>',
    ];
    private const AUTO_CONTENT = [
        '//main',
        "//*[@role='main']",
        "//*[@id='main']",
        "//*[@id='content']",
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' page-content ')]",
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' main-content ')]",
    ];

    /**
     * @param string $entry пътят на документа в пакета ('' за макет без пакет)
     * @param array<string, true> $packageFiles файловете на пакета по относителен път
     * @return array{code: string, report: list<string>, vendor: array<string, string>, files: array<string, string>} vendor: файл от public => път в public/templates/<име>/; files: изнесените из HTML файлове (път => съдържание)
     * @throws AiException
     */
    public function build(string $html, string $layoutName, string $contentSelector = '', string $entry = '', array $packageFiles = []): array
    {
        $prefix = 'templates/' . $layoutName;
        $directory = str_contains($entry, '/') ? dirname($entry) : '';
        $report = [];
        $vendor = [];
        $files = [];
        $inlineJs = [];

        $html = preg_replace('/<\?.*?\?>/s', '', $html) ?? $html;
        $dom = HtmlSanitizer::load($html);
        $xpath = new \DOMXPath($dom);

        $head = $xpath->query('//head')->item(0);
        $body = $xpath->query('//body')->item(0);
        if (!$head instanceof \DOMElement) {
            $head = $dom->createElement('head');
            $root = $xpath->query('//html')->item(0);
            if (!$root instanceof \DOMElement || !$body instanceof \DOMElement) {
                throw new AiException('Документът няма <html> и <body>.');
            }
            $root->insertBefore($head, $root->firstChild);
        }
        if (!$body instanceof \DOMElement) {
            throw new AiException('Документът няма <body>.');
        }

        // Съдържанието се определя преди да се пипа останалото, за да не изчезне заедно с махнати елементи.
        $content = $this->contentElement($xpath, $contentSelector);
        $report[] = 'Област за съдържание: <' . $content->tagName . ($content->getAttribute('class') !== '' ? ' class="' . $content->getAttribute('class') . '"' : '') . '>. Съдържанието ѝ се замества със секция "content" на изгледите.';
        while ($content->firstChild !== null) {
            $content->removeChild($content->firstChild);
        }
        $content->appendChild($dom->createTextNode('@@AIIMPL_CONTENT@@'));

        $removedOther = 0;
        $removedExternal = [];
        foreach (iterator_to_array($xpath->query('//script') ?: []) as $script) {
            $src = trim($script->getAttribute('src'));
            $local = $src === '' ? null : TemplatePreview::localPath($directory, $src);
            if ($local !== null && isset($packageFiles[$local]) && $this->isScriptFile($local)) {
                $script->setAttribute('src', $prefix . '/' . $local);
                $script->removeAttribute('integrity');
                $script->removeAttribute('crossorigin');
                continue;
            }
            if ($src === '') {
                // Inline JavaScript се изнася в отделен файл; другите типове (JSON-LD, шаблони) се махат.
                $type = strtolower(trim($script->getAttribute('type')));
                if (in_array($type, ['', 'text/javascript', 'application/javascript'], true)) {
                    $inlineJs[] = trim($script->textContent);
                } else {
                    $removedOther++;
                }
            } else {
                $removedExternal[] = $src;
            }
            $script->parentNode?->removeChild($script);
        }
        $inlineJs = array_values(array_filter($inlineJs, static fn (string $js): bool => $js !== ''));
        if ($removedOther > 0) {
            $report[] = "Премахнати скриптове от друг тип (не JavaScript): {$removedOther}.";
        }
        if ($removedExternal !== []) {
            $report[] = 'Премахнати скриптове от външни адреси (добавете ги ръчно, ако са нужни): ' . implode(', ', array_slice($removedExternal, 0, 8)) . (count($removedExternal) > 8 ? ' ...' : '');
        }

        foreach (iterator_to_array($xpath->query('//iframe|//frame|//frameset|//object|//embed|//applet|//base|//noscript|//template|//meta[@http-equiv]') ?: []) as $element) {
            $element->parentNode?->removeChild($element);
        }

        $usesBootstrap = false;
        $externalStyles = [];
        foreach (iterator_to_array($xpath->query('//link') ?: []) as $link) {
            $href = trim($link->getAttribute('href'));
            $rel = strtolower($link->getAttribute('rel'));
            $local = TemplatePreview::localPath($directory, $href);

            if ($href === HtmlSanitizer::BOOTSTRAP_CSS && $rel === 'stylesheet') {
                $link->setAttribute('href', $prefix . '/css/bootstrap.min.css');
                $vendor[HtmlSanitizer::BOOTSTRAP_CSS] = 'css/bootstrap.min.css';
                $usesBootstrap = true;
            } elseif (in_array($rel, ['stylesheet', 'icon', 'shortcut icon'], true) && $local !== null && isset($packageFiles[$local])) {
                $link->setAttribute('href', $prefix . '/' . $local);
                $link->removeAttribute('integrity');
            } elseif ($rel === 'stylesheet' && preg_match('~^https://~i', $href) === 1) {
                $link->removeAttribute('integrity');
                $externalStyles[] = $href;
            } else {
                $link->parentNode?->removeChild($link);
            }
        }
        if ($externalStyles !== []) {
            $report[] = 'Остават стилове от външни адреси (проверете ги): ' . implode(', ', array_slice($externalStyles, 0, 5));
        }

        $rewritten = 0;
        foreach (iterator_to_array($xpath->query('//*[@src or @poster or @srcset]') ?: []) as $element) {
            foreach (['src', 'poster'] as $attribute) {
                if ($element->hasAttribute($attribute) && strtolower($element->tagName) !== 'script') {
                    $rewritten += $this->rewriteAttribute($element, $attribute, $directory, $prefix, $packageFiles);
                }
            }
            if ($element->hasAttribute('srcset')) {
                $parts = [];
                foreach (explode(',', $element->getAttribute('srcset')) as $candidate) {
                    $bits = preg_split('/\s+/', trim($candidate), 2) ?: [];
                    $local = TemplatePreview::localPath($directory, (string) ($bits[0] ?? ''));
                    $parts[] = ($local !== null && isset($packageFiles[$local]) ? $prefix . '/' . $local : (string) ($bits[0] ?? '')) . (isset($bits[1]) ? ' ' . $bits[1] : '');
                }
                $element->setAttribute('srcset', implode(', ', $parts));
            }
        }

        $pageLinks = 0;
        foreach (iterator_to_array($xpath->query('//*[@href or @action or @formaction or @style or @*[starts-with(name(), "on")]]') ?: []) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = $attribute->nodeValue ?? '';

                if (str_starts_with($name, 'on') || $name === 'action' || $name === 'formaction' || ($name === 'href' && !HtmlSanitizer::safeUrl($value))) {
                    $element->removeAttribute($attribute->nodeName);
                } elseif ($name === 'style') {
                    $element->setAttribute('style', $this->rewriteCssUrls(HtmlSanitizer::cleanCss($value), $directory, $prefix, $packageFiles));
                } elseif ($name === 'href' && preg_match('~\.html?(?:[?#].*)?$~i', $value) === 1 && TemplatePreview::localPath($directory, $value) !== null) {
                    $pageLinks++;
                }
            }
        }
        // Всички <style> блокове се събират в един файл; url() в него е спрямо css/ (../), а не спрямо <base>.
        $css = [];
        $styles = iterator_to_array($xpath->query('//style[not(ancestor::*[local-name()="svg"])]') ?: []);
        foreach ($styles as $style) {
            $text = trim($this->rewriteCssUrls(HtmlSanitizer::cleanCss($style->textContent), $directory, '..', $packageFiles));
            if ($text !== '') {
                $css[] = $text;
            }
        }
        if ($css !== []) {
            $cssPath = isset($packageFiles['css/layout.css']) ? 'css/layout.inline.css' : 'css/layout.css';
            $files[$cssPath] = implode("\n\n", $css) . "\n";
            $link = $dom->createElement('link');
            $link->setAttribute('rel', 'stylesheet');
            $link->setAttribute('href', $prefix . '/' . $cssPath);
            $styles[0]->parentNode?->insertBefore($link, $styles[0]);
            $report[] = 'CSS от ' . count($css) . ' <style> блока е изнесен в ' . $prefix . '/' . $cssPath . '.';
        }
        foreach ($styles as $style) {
            $style->parentNode?->removeChild($style);
        }
        if ($rewritten > 0) {
            $report[] = "Адреси на файлове, пренасочени към public/{$prefix}/: {$rewritten}.";
        }
        if ($pageLinks > 0) {
            $report[] = "Връзки към други HTML страници на шаблона: {$pageLinks}. Оставени са без промяна; сменете ги с адресите на сайта.";
        }

        $this->ensureMeta($dom, $xpath, $head);
        $head->insertBefore($dom->createTextNode('@@AIIMPL_BASE@@'), $head->firstChild);

        $title = $xpath->query('//title')->item(0);
        if (!$title instanceof \DOMElement) {
            $title = $dom->createElement('title');
            $head->appendChild($title);
        }
        $title->textContent = '@@AIIMPL_TITLE@@';

        $head->appendChild($dom->createTextNode('@@AIIMPL_STYLES@@'));

        if ($usesBootstrap) {
            $script = $dom->createElement('script');
            $script->setAttribute('src', $prefix . '/js/bootstrap.bundle.min.js');
            $body->appendChild($script);
            $vendor[HtmlSanitizer::BOOTSTRAP_JS] = 'js/bootstrap.bundle.min.js';
        }
        if ($inlineJs !== []) {
            $jsPath = isset($packageFiles['js/layout.js']) ? 'js/layout.inline.js' : 'js/layout.js';
            $files[$jsPath] = implode("\n\n", $inlineJs) . "\n";
            $script = $dom->createElement('script');
            $script->setAttribute('src', $prefix . '/' . $jsPath);
            $body->appendChild($script);
            $report[] = 'JavaScript от ' . count($inlineJs) . ' inline блока е изнесен в ' . $prefix . '/' . $jsPath . ' и се зарежда в края на страницата (преди секция "scripts"). Код, който зависи от място в <head>, може да изисква преместване.';
        }
        $body->appendChild($dom->createTextNode('@@AIIMPL_SCRIPTS@@'));

        $out = HtmlSanitizer::save($dom);
        // Последна проверка: никакъв PHP от шаблона не стига до файла.
        $out = str_replace('<?', '&lt;?', $out);
        foreach (self::PLACEHOLDERS as $placeholder) {
            if (substr_count($out, $placeholder) !== 1) {
                throw new AiException('Layout не може да бъде построен: липсва място за ' . $placeholder . '.');
            }
        }

        return [
            'code' => '<?php /* ' . self::MARK . ': ' . $layoutName . ' */ ?>' . "\n" . str_replace(self::PLACEHOLDERS, self::SNIPPETS, $out) . "\n",
            'report' => $report,
            'vendor' => $vendor,
            'files' => $files,
        ];
    }

    /** @throws AiException */
    private function contentElement(\DOMXPath $xpath, string $selector): \DOMElement
    {
        $queries = self::AUTO_CONTENT;
        if (trim($selector) !== '') {
            if (preg_match('/^([a-z][a-z0-9]*)?(?:([#.])([A-Za-z_][\w-]*))?$/', trim($selector), $m) !== 1 || trim($selector) === '') {
                throw new AiException('Селекторът е във вида tag, #id, .клас или tag.клас.');
            }
            $tag = ($m[1] ?? '') !== '' ? $m[1] : '*';
            $queries = [match ($m[2] ?? '') {
                '#' => "//{$tag}[@id='{$m[3]}']",
                '.' => "//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$m[3]} ')]",
                default => "//{$tag}",
            }];
        }

        foreach ($queries as $query) {
            $element = $xpath->query($query)->item(0);
            if ($element instanceof \DOMElement && !in_array(strtolower($element->tagName), ['html', 'body', 'head'], true)) {
                return $element;
            }
        }

        throw new AiException('Не е намерена област за съдържание. Задайте селектор (напр. main, #content, .page-content).');
    }

    private function ensureMeta(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $head): void
    {
        if ($xpath->query('//meta[@charset]')->length === 0) {
            $meta = $dom->createElement('meta');
            $meta->setAttribute('charset', 'utf-8');
            $head->insertBefore($meta, $head->firstChild);
        }
        if ($xpath->query('//meta[@name="viewport"]')->length === 0) {
            $meta = $dom->createElement('meta');
            $meta->setAttribute('name', 'viewport');
            $meta->setAttribute('content', 'width=device-width, initial-scale=1');
            $head->appendChild($meta);
        }
    }

    /** @param array<string, true> $packageFiles */
    private function rewriteAttribute(\DOMElement $element, string $attribute, string $directory, string $prefix, array $packageFiles): int
    {
        $local = TemplatePreview::localPath($directory, $element->getAttribute($attribute));
        if ($local === null || !isset($packageFiles[$local])) {
            return 0;
        }
        $element->setAttribute($attribute, $prefix . '/' . $local);

        return 1;
    }

    /** @param array<string, true> $packageFiles */
    private function rewriteCssUrls(string $css, string $directory, string $prefix, array $packageFiles): string
    {
        return preg_replace_callback('/url\(\s*([\'"]?)([^)\'"]+)\1\s*\)/i', static function (array $m) use ($directory, $prefix, $packageFiles): string {
            $local = TemplatePreview::localPath($directory, $m[2]);

            return $local !== null && isset($packageFiles[$local]) ? 'url(' . $prefix . '/' . $local . ')' : $m[0];
        }, $css) ?? $css;
    }

    private function isScriptFile(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'js';
    }
}
