<?php

declare(strict_types=1);

namespace Ai\File;

use Ai\Exception\AiException;

/** Изважда текст от качен файл: txt/md/csv/json, docx, xlsx и (ако е инсталиран smalot/pdfparser) pdf. */
final class FileTextExtractor
{
    public const EXTENSIONS = ['txt', 'md', 'csv', 'json', 'docx', 'xlsx', 'pdf'];
    public const MAX_BYTES = 10_000_000;

    // Ограничения срещу "zip бомби" и огромни таблици: проверяват се преди разархивиране и четене.
    private const MAX_ENTRY_BYTES = 10_000_000;
    private const MAX_SHEETS = 3;
    private const MAX_ROWS = 2000;
    private const MAX_COLUMNS = 50;

    public function extract(string $path, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new AiException('Неподдържан тип файл. Позволени: ' . implode(', ', self::EXTENSIONS) . '.');
        }

        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new AiException('Файлът е празен или не може да се прочете.');
        }
        if ($size > self::MAX_BYTES) {
            throw new AiException(sprintf('Файлът е твърде голям (максимум %d MB).', self::MAX_BYTES / 1_000_000));
        }

        $text = match ($extension) {
            'docx' => $this->docx($path),
            'xlsx' => $this->xlsx($path),
            'pdf' => $this->pdf($path),
            default => $this->plain($path),
        };

        // Табулацията се пази: в XLSX и CSV тя разделя колоните.
        $text = trim(preg_replace('/ {2,}/', ' ', preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? '') ?? '');
        if ($text === '') {
            throw new AiException('Във файла не е намерен текст.');
        }

        return $text;
    }

    private function plain(string $path): string
    {
        $raw = (string) file_get_contents($path, false, null, 0, self::MAX_BYTES);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        // Български CSV/TXT често са в Windows-1251.
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
    }

    private function docx(string $path): string
    {
        $zip = $this->openZip($path);
        $xml = $this->entry($zip, 'word/document.xml');
        $zip->close();

        $xml = preg_replace(['#</w:p>#', '#<w:(?:tab|br)\b[^>]*/>#'], ["\n", ' '], $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xlsx(string $path): string
    {
        $zip = $this->openZip($path);

        $strings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $shared = $this->xml($this->entry($zip, 'xl/sharedStrings.xml'));
            foreach ($shared->si ?? [] as $si) {
                $strings[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
            }
        }

        $sheets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', (string) $zip->getNameIndex($i), $m) === 1) {
                $sheets[(int) $m[1]] = $m[0];
            }
        }
        ksort($sheets);

        $out = [];
        foreach (array_slice($sheets, 0, self::MAX_SHEETS, true) as $number => $name) {
            $sheet = $this->xml($this->entry($zip, $name));
            $out[] = '## Лист ' . $number;
            $rows = 0;
            foreach ($sheet->sheetData->row ?? [] as $row) {
                if (++$rows > self::MAX_ROWS) {
                    $out[] = '[таблицата е съкратена]';
                    break;
                }
                $cells = [];
                foreach ($row->c as $c) {
                    $column = $this->columnIndex((string) $c['r']);
                    if ($column >= self::MAX_COLUMNS) {
                        continue;
                    }
                    $cells[$column] = match ((string) $c['t']) {
                        's' => $strings[(int) $c->v] ?? '',
                        'inlineStr' => implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]') ?: [])),
                        'b' => (string) $c->v === '1' ? 'да' : 'не',
                        default => (string) $c->v,
                    };
                }
                if ($cells !== []) {
                    ksort($cells);
                    $line = [];
                    for ($i = 0, $last = array_key_last($cells); $i <= $last; $i++) {
                        $line[] = str_replace(["\t", "\n", "\r"], ' ', $cells[$i] ?? '');
                    }
                    $out[] = implode("\t", $line);
                }
            }
        }
        $zip->close();

        return implode("\n", $out);
    }

    private function pdf(string $path): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new AiException('Четенето на PDF изисква пакета smalot/pdfparser (composer require smalot/pdfparser).');
        }
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new AiException('Файлът не е валиден PDF.');
        }

        try {
            $text = (new \Smalot\PdfParser\Parser())->parseFile($path)->getText();
        } catch (\Throwable) {
            throw new AiException('PDF файлът не може да бъде прочетен (повреден или защитен с парола).');
        }
        if (trim($text) === '') {
            throw new AiException('PDF файлът няма текстов слой (вероятно е сканиран). Нужен е OCR.');
        }

        return $text;
    }

    private function openZip(string $path): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new AiException('Файлът е повреден или не е валиден Office документ.');
        }

        return $zip;
    }

    private function entry(\ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw new AiException('Файлът не съдържа очакваната структура на Office документ.');
        }
        if ($stat['size'] > self::MAX_ENTRY_BYTES) {
            throw new AiException('Съдържанието на файла е прекалено голямо.');
        }

        return (string) $zip->getFromName($name);
    }

    private function xml(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $parsed ?: throw new AiException('XLSX файлът не може да бъде прочетен.');
    }

    /** "C5" => 2 */
    private function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $m);
        $index = 0;
        foreach (str_split($m[0] ?? 'A') as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
