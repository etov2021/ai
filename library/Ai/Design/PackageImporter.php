<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;

/** Безопасно разархивиране на пакет с шаблон (HTML, CSS, JS, картинки, шрифтове). Всичко извън белия списък се пропуска. */
final class PackageImporter
{
    /** Разширения, които се запазват. Няма php, phtml, htaccess, изпълними и архивни файлове. */
    public const ALLOWED = ['html', 'htm', 'css', 'js', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot'];
    private const MAX_FILES = 1500;
    private const MAX_FILE_BYTES = 8_000_000;
    private const MAX_TOTAL_BYTES = 60_000_000;

    /**
     * @return array{entry: string, files: int, skipped: int}
     * @throws AiException
     */
    public function import(string $zipPath, string $targetDir): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new AiException('Файлът не е валиден ZIP архив.');
        }

        try {
            if ($zip->numFiles > self::MAX_FILES * 4) {
                throw new AiException('Архивът съдържа прекалено много файлове.');
            }

            $accepted = [];
            $skipped = 0;
            $declared = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat === false ? '' : self::cleanPath((string) $stat['name']);
                if ($stat === false || str_ends_with((string) $stat['name'], '/')) {
                    continue;
                }
                if ($name === null || !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::ALLOWED, true) || $stat['size'] > self::MAX_FILE_BYTES) {
                    $skipped++;
                    continue;
                }
                $declared += (int) $stat['size'];
                $accepted[$i] = $name;
            }

            if ($accepted === []) {
                throw new AiException('В архива няма поддържани файлове (HTML, CSS, JS, картинки, шрифтове).');
            }

            // Пакетите често са във една обща папка (напр. шаблон-1.0/): махаме я, за да са пътищата кратки.
            $tops = array_unique(array_map(static fn (string $n): string => explode('/', $n)[0], $accepted));
            if (count($tops) === 1 && count(array_filter($accepted, static fn (string $n): bool => !str_contains($n, '/'))) === 0) {
                $accepted = array_map(static fn (string $n): string => substr($n, strlen($tops[array_key_first($tops)]) + 1), $accepted);
            }
            if (count($accepted) > self::MAX_FILES || $declared > self::MAX_TOTAL_BYTES) {
                throw new AiException('Архивът е прекалено голям (до ' . self::MAX_FILES . ' файла и ' . (int) (self::MAX_TOTAL_BYTES / 1_000_000) . ' MB).');
            }

            $total = 0;
            foreach ($accepted as $index => $name) {
                $path = $targetDir . '/' . $name;
                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                    throw new AiException('Папката за пакета не може да бъде създадена.');
                }

                $in = $zip->getStream($zip->getNameIndex($index));
                $out = fopen($path, 'wb');
                if ($in === false || $out === false) {
                    throw new AiException('Файл от архива не може да бъде записан.');
                }
                // Декларираният размер в архива не е гаранция (zip bomb): броят се реално записаното.
                $written = 0;
                while (!feof($in)) {
                    $chunk = (string) fread($in, 65536);
                    $written += strlen($chunk);
                    $total += strlen($chunk);
                    if ($written > self::MAX_FILE_BYTES || $total > self::MAX_TOTAL_BYTES) {
                        fclose($in);
                        fclose($out);
                        throw new AiException('Архивът се разархивира до прекалено голям размер.');
                    }
                    fwrite($out, $chunk);
                }
                fclose($in);
                fclose($out);
            }
        } finally {
            $zip->close();
        }

        return ['entry' => self::findEntry(array_values($accepted)), 'files' => count($accepted), 'skipped' => $skipped];
    }

    /** @return ?string нормализиран относителен път или null за опасен и скрит */
    public static function cleanPath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~^[A-Za-z]:~', $name) === 1) {
            return null;
        }

        $parts = [];
        foreach (explode('/', $name) as $part) {
            // Скритите файлове и папките на macOS не са част от шаблона; .. би излязло от папката (zip slip).
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || $part === '__MACOSX') {
                return null;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /** Главният документ: най-плиткият index.html, иначе най-плиткият HTML файл. */
    public static function findEntry(array $files): string
    {
        $html = array_values(array_filter($files, static fn (string $f): bool => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['html', 'htm'], true)));
        if ($html === []) {
            throw new AiException('В архива няма HTML файл.');
        }

        usort($html, static function (string $a, string $b): int {
            $da = substr_count($a, '/');
            $db = substr_count($b, '/');
            $ia = preg_match('~(^|/)index\.html?$~i', $a) === 1 ? 0 : 1;
            $ib = preg_match('~(^|/)index\.html?$~i', $b) === 1 ? 0 : 1;

            return [$ia, $da, $a] <=> [$ib, $db, $b];
        });

        return $html[0];
    }
}
