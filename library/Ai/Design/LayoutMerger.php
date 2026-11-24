<?php

declare(strict_types=1);

namespace Ai\Design;

/**
 * Презаписва само HTML-а на layout, който дизайнерът вече е създал: PHP кодът, добавен ръчно във файла, се запазва.
 *
 * Ръчен PHP е всеки <?php ... ?> и <?= ... ?>, който не е маркерът или фиксиран откъс на дизайнера. Той се връща на мястото си в новия HTML
 * по текста около него: вмъква се между същите две парчета HTML, а ако между тях в новия HTML има само друг текст на мястото на стария
 * (напр. href="#" вместо href="<?php ... ?>"), заменя го. Блок, чието място не може да се намери със сигурност, се връща като непоставен.
 */
final class LayoutMerger
{
    private const ANCHOR = 80;
    private const MAX_REPLACED_GAP = 200;

    /**
     * @param string $old текущият файл на layout-а
     * @param string $new новият код от LayoutBuilder
     * @return array{code: string, preserved: int, replaced: int, unplaced: list<string>}
     */
    public function merge(string $old, string $new): array
    {
        $parts = preg_split('/(<\?(?:php|=)(?:.*?\?>|.*$))/s', $old, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$old];

        // Редуват се текст и PHP блок; фиксираните откъси остават част от текста, за да служат за опорни точки.
        $segments = [];
        foreach ($parts as $i => $part) {
            $custom = $i % 2 === 1 && !self::isKnown($part);
            $last = array_key_last($segments);

            if ($custom && $last !== null && $segments[$last]['custom']) {
                $segments[$last]['text'] .= $part;
            } elseif ($custom && $last !== null && $last >= 1 && !$segments[$last]['custom'] && trim($segments[$last]['text']) === '' && $segments[$last - 1]['custom']) {
                // Само интервали между два ръчни блока: те са един блок.
                $segments[$last - 1]['text'] .= $segments[$last]['text'] . $part;
                array_pop($segments);
            } elseif ($custom) {
                $segments[] = ['custom' => true, 'text' => $part];
            } elseif ($last !== null && !$segments[$last]['custom']) {
                $segments[$last]['text'] .= $part;
            } else {
                $segments[] = ['custom' => false, 'text' => $part];
            }
        }
        $merged = $segments;
        $edits = [];
        $unplaced = [];
        $position = 0;
        $replaced = 0;
        $count = count($merged);
        for ($index = 0; $index < $count; $index++) {
            if (!$merged[$index]['custom']) {
                continue;
            }

            $beforeText = $merged[$index - 1]['text'] ?? '';

            $start = $position;
            if (trim($beforeText) !== '') {
                $found = $this->locate($new, $beforeText, $position, true);
                if ($found === null) {
                    $unplaced[] = $this->label($merged[$index]['text']);
                    continue;
                }
                $start = $found['end'];
            }

            // Ако текстът след блока го няма в новия HTML, а след него идва друг ръчен блок, това е HTML, добавен ръчно
            // заедно с PHP (напр. условие около връзка): целият участък се пази като едно цяло.
            $region = $merged[$index]['text'];
            $last = $index;
            $end = null;
            while (true) {
                $afterText = $merged[$last + 1]['text'] ?? '';
                if (trim($afterText) === '') {
                    $end = $start;
                    break;
                }
                $found = $this->locate($new, $afterText, $start, false);
                if ($found !== null) {
                    $end = $found['start'];
                    break;
                }
                if (isset($merged[$last + 2]) && $merged[$last + 2]['custom']) {
                    $region .= $afterText . $merged[$last + 2]['text'];
                    $last += 2;
                    continue;
                }
                break;
            }

            $gap = $end === null ? '' : substr($new, $start, $end - $start);
            if ($end === null || ($gap !== '' && !$this->replaceable($gap))) {
                $unplaced[] = $this->label($region);
                $index = $last;
                continue;
            }

            $replaced += $gap === '' ? 0 : 1;
            $edits[] = [$start, $end, $region];
            $position = $end;
            $index = $last;
        }

        $code = '';
        $cursor = 0;
        foreach ($edits as [$from, $to, $text]) {
            $code .= substr($new, $cursor, $from - $cursor) . $text;
            $cursor = $to;
        }
        $code .= substr($new, $cursor);

        return ['code' => $code, 'preserved' => count($edits), 'replaced' => $replaced, 'unplaced' => $unplaced];
    }

    /**
     * Търси опорен текст (края на $text при $tail, иначе началото). Ако HTML-ът наблизо е променен,
     * дългият текст не съвпада, затова се опитва със по-кратък до 12 знака.
     */
    private function locate(string $haystack, string $text, int $from, bool $tail): ?array
    {
        foreach ([self::ANCHOR, 40, 24, 12] as $length) {
            $anchor = $tail ? mb_substr($text, -$length) : mb_substr($text, 0, $length);
            $found = $this->find($haystack, $anchor, $from);
            if ($found !== null) {
                return $found;
            }
            if (mb_strlen($text) <= $length) {
                break;
            }
        }

        return null;
    }

    /** Търси опорния текст, като интервалите са гъвкави (редакторът може да е променил отстъпите). */
    private function find(string $haystack, string $anchor, int $from): ?array
    {
        $tokens = preg_split('/\s+/', trim($anchor), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return null;
        }
        $pattern = '~' . implode('\s*', array_map(static fn (string $t): string => preg_quote($t, '~'), $tokens)) . '~u';

        return preg_match($pattern, $haystack, $m, PREG_OFFSET_CAPTURE, $from) === 1
            ? ['start' => (int) $m[0][1], 'end' => (int) $m[0][1] + strlen($m[0][0])]
            : null;
    }

    /** Между двата опорни текста в новия HTML има само различен текст (без етикети): той е бил заменен от PHP. */
    private function replaceable(string $gap): bool
    {
        $rest = trim(str_replace(self::knownBlocks(), '', $gap));

        return strlen($rest) <= self::MAX_REPLACED_GAP && !str_contains($rest, '<');
    }

    private function label(string $block): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $block)), 0, 90);
    }

    private static function isKnown(string $block): bool
    {
        if (preg_match('/^<\?php\s*\/\*\s*' . preg_quote(LayoutBuilder::MARK, '/') . ':[^*]*\*\/\s*\?>$/', trim($block)) === 1) {
            return true;
        }

        return in_array(self::normalize($block), array_map(self::normalize(...), self::knownBlocks()), true);
    }

    /**
     * PHP блоковете на фиксираните откъси на дизайнера (секциите title, styles, content, scripts и base).
     *
     * @return list<string>
     */
    private static function knownBlocks(): array
    {
        static $blocks = null;
        if ($blocks === null) {
            $blocks = [];
            foreach (LayoutBuilder::snippets() as $snippet) {
                preg_match_all('/<\?(?:php|=).*?\?>/s', $snippet, $m);
                array_push($blocks, ...$m[0]);
            }
        }

        return $blocks;
    }

    private static function normalize(string $code): string
    {
        return (string) preg_replace('/\s+/', '', $code);
    }
}
