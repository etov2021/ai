<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Security\Policy;

/**
 * Сваля чужда страница и я свива до кратко описание на структурата и стила, което влиза в заявката към агента.
 * Само публични https адреси; редиректите се следват ръчно и всеки се проверява наново (SSRF).
 */
final class ReferenceFetcher
{
    public const MAX_SUMMARY_CHARS = 13000;
    private const MAX_REDIRECTS = 3;
    private const MAX_STYLESHEETS = 3;
    private const OUTLINE_CHARS = 7000;
    private const CSS_EXCERPT_CHARS = 3000;
    private const MAX_DEPTH = 7;
    private const SKIP_TAGS = ['script', 'style', 'noscript', 'link', 'meta', 'head', 'template', 'iframe', 'object', 'embed', 'path', 'defs'];

    public function __construct(private HttpClientInterface $http, private Policy $policy, private int $timeout = 15)
    {
    }

    /** @throws AiException */
    public function summarize(string $url): string
    {
        $page = $this->get($url, true);

        return $this->summarizeHtml(self::utf8($page['body']), function (string $href) use ($page): ?string {
            try {
                return self::utf8($this->get(self::resolve($page['url'], $href), false)['body']);
            } catch (AiException) {
                return null;
            }
        });
    }

    /**
     * Свива готов HTML (от страница, поставен код или пакет) до описанието за агента.
     *
     * @param ?\Closure(string): ?string $stylesheet връща текста на свързан стилов файл по адреса от страницата; null = недостъпен
     */
    public function summarizeHtml(string $html, ?\Closure $stylesheet = null): string
    {
        $dom = HtmlSanitizer::load($html);

        $xpath = new \DOMXPath($dom);
        $title = trim((string) $xpath->evaluate('string(//title)'));

        $css = '';
        foreach ($xpath->query('//style') ?: [] as $style) {
            $css .= "\n" . $style->textContent;
        }
        $fetched = 0;
        foreach ($stylesheet === null ? [] : ($xpath->query('//link[@rel="stylesheet"]/@href') ?: []) as $href) {
            if ($fetched >= self::MAX_STYLESHEETS) {
                break;
            }
            // Недостъпен стилов файл не е причина да се откаже целият образец.
            $text = $stylesheet(trim($href->nodeValue ?? ''));
            if ($text !== null) {
                $css .= "\n" . $text;
                $fetched++;
            }
        }

        $body = $xpath->query('//body')->item(0);
        $budget = self::OUTLINE_CHARS;
        $outline = $body instanceof \DOMElement ? self::outline($body, 0, $budget) : '';

        $summary = "Заглавие на страницата: " . mb_substr($title, 0, 150) . "\n\n"
            . "Структура (таг, класове, начало на текста; еднакви съседни елементи са свити):\n" . $outline . "\n"
            . self::styleDigest($css);

        return mb_substr($summary, 0, self::MAX_SUMMARY_CHARS);
    }

    /** @return array{url: string, body: string} */
    private function get(string $url, bool $html): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertPublicHttps($url);

            try {
                $response = $this->http->get($url, [
                    'User-Agent' => 'Mozilla/5.0 (compatible; aiimpl-design/1.0)',
                    'Accept' => $html ? 'text/html,application/xhtml+xml' : 'text/css,*/*;q=0.1',
                ], $this->timeout);
            } catch (HttpException) {
                throw new AiException('Страницата не отговаря.');
            }

            if (in_array($response->status, [301, 302, 303, 307, 308], true) && isset($response->headers['location'])) {
                $url = self::resolve($url, $response->headers['location']);
                continue;
            }
            if ($response->status !== 200) {
                throw new AiException(sprintf('Страницата върна HTTP %d.', $response->status));
            }
            if ($html && !str_contains(strtolower($response->headers['content-type'] ?? 'text/html'), 'html')) {
                throw new AiException('Адресът не сочи към HTML страница.');
            }

            return ['url' => $url, 'body' => $response->body];
        }

        throw new AiException('Прекалено много пренасочвания.');
    }

    private function assertPublicHttps(string $url): void
    {
        if (strlen($url) > 500 || !str_starts_with(strtolower($url), 'https://')) {
            throw new AiException('Адресът на образеца трябва да е https и до 500 знака.');
        }
        $this->policy->assertBaseUrl($url);
    }

    public static function utf8(string $body): string
    {
        return mb_check_encoding($body, 'UTF-8') ? $body : mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
    }

    /** Превръща относителен адрес в пълен спрямо страницата, от която идва. */
    public static function resolve(string $base, string $href): string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return $base;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $href;
        }
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $href) === 1) {
            return $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $directory = substr($parts['path'] ?? '/', 0, (int) strrpos($parts['path'] ?? '/', '/') + 1);
        $segments = [];
        foreach (explode('/', $directory . $href) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $origin . implode('/', $segments);
    }

    /** Дървото на страницата в кратък вид: един ред на елемент, повтарящите се съседи се свиват. */
    private static function outline(\DOMElement $element, int $depth, int &$budget): string
    {
        if ($budget <= 0) {
            return '';
        }

        $classes = implode('.', array_slice(preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], 0, 3));
        $line = str_repeat(' ', $depth) . '<' . $element->tagName . ($element->hasAttribute('id') ? '#' . mb_substr($element->getAttribute('id'), 0, 30) : '') . ($classes !== '' ? '.' . $classes : '') . '>';

        $text = '';
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text .= ' ' . $child->textContent;
            } elseif ($child instanceof \DOMElement && !in_array(strtolower($child->tagName), self::SKIP_TAGS, true)) {
                $children[] = $child;
            }
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text !== '') {
            $line .= ' "' . mb_substr($text, 0, 50) . '"';
        }

        $out = $line . "\n";
        $budget -= strlen($out);

        if ($depth >= self::MAX_DEPTH || strtolower($element->tagName) === 'svg') {
            return $out;
        }

        $previous = null;
        $repeat = 0;
        foreach ($children as $child) {
            $signature = $child->tagName . '.' . strtok($child->getAttribute('class') . ' ', ' ');
            if ($signature === $previous) {
                if (++$repeat > 2) {
                    continue;
                }
            } else {
                self::flushRepeat($out, $repeat, $depth, $budget);
                $previous = $signature;
                $repeat = 1;
            }
            $out .= self::outline($child, $depth + 1, $budget);
        }
        self::flushRepeat($out, $repeat, $depth, $budget);

        return $out;
    }

    private static function flushRepeat(string &$out, int &$repeat, int $depth, int &$budget): void
    {
        if ($repeat > 2) {
            $note = str_repeat(' ', $depth + 1) . '... още ' . ($repeat - 2) . " подобни\n";
            $out .= $note;
            $budget -= strlen($note);
        }
        $repeat = 0;
    }

    /** Палитра, шрифтове и заоблявания от стиловете, плюс откъс от правилата: достатъчно за усещането, без стотици KB CSS. */
    private static function styleDigest(string $css): string
    {
        $css = preg_replace(['~/\*.*?\*/~s', '~@(?:import|charset)[^;]*;~i', '~@font-face\s*\{[^}]*\}~i', '~@(?:-webkit-)?keyframes[^{]*\{(?:[^{}]|\{[^}]*\})*\}~i'], '', $css) ?? $css;
        $css = trim(preg_replace('/\s+/', ' ', $css) ?? '');

        $colors = [];
        preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b|rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+[^)]*\)/', $css, $m);
        foreach ($m[0] as $color) {
            $key = strtolower(preg_replace('/\s+/', '', $color) ?? $color);
            $colors[$key] = ($colors[$key] ?? 0) + 1;
        }
        arsort($colors);

        $fonts = [];
        preg_match_all('/font-family\s*:\s*([^;}]+)/i', $css, $m);
        foreach ($m[1] as $font) {
            $fonts[trim($font)] = ($fonts[trim($font)] ?? 0) + 1;
        }
        arsort($fonts);

        $radii = [];
        preg_match_all('/border-radius\s*:\s*([^;}]+)/i', $css, $m);
        foreach ($m[1] as $radius) {
            $radii[trim($radius)] = ($radii[trim($radius)] ?? 0) + 1;
        }
        arsort($radii);

        return "Най-чести цветове: " . implode(', ', array_slice(array_keys($colors), 0, 10)) . "\n"
            . 'Шрифтове: ' . implode(' | ', array_slice(array_keys($fonts), 0, 3)) . "\n"
            . 'Заобляния: ' . implode(' | ', array_slice(array_keys($radii), 0, 4)) . "\n"
            . "Откъс от стиловете:\n" . mb_substr($css, 0, self::CSS_EXCERPT_CHARS);
    }
}
