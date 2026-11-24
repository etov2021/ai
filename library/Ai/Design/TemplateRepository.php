<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Шаблоните в библиотеката: записите са в базата, а файловете на пакетите са в $packageRoot/<id>/ (извън public). */
final class TemplateRepository
{
    public function __construct(private DatabaseInterface $db, private string $packageRoot)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT `id`, `name`, `tags`, `source`, `sourceUrl`, `hasPackage`, `created` FROM `AiUiTemplates` ORDER BY `id` DESC LIMIT 300');
    }

    /** @return ?array{id: int, name: string, tags: string, source: string, sourceUrl: ?string, entry: ?string, html: ?string, hasPrevious: bool, summary: string, hasPackage: bool, created: string} */
    public function find(int $id): ?array
    {
        $row = $this->db->fetchRow('SELECT * FROM `AiUiTemplates` WHERE `id` = ?', [$id]);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'tags' => (string) $row['tags'],
            'source' => (string) $row['source'],
            'sourceUrl' => $row['sourceUrl'] === null ? null : (string) $row['sourceUrl'],
            'entry' => $row['entry'] === null ? null : (string) $row['entry'],
            'html' => $row['html'] === null ? null : (string) $row['html'],
            // Колоната е от миграция 017: преди нея върщането просто не е налично.
            'hasPrevious' => ($row['previousHtml'] ?? null) !== null,
            'summary' => (string) $row['summary'],
            'hasPackage' => (bool) $row['hasPackage'],
            'created' => (string) $row['created'],
        ];
    }

    public function create(string $name, string $tags, string $source, ?string $sourceUrl, ?string $entry, ?string $html, string $summary, bool $hasPackage, ?int $userId): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new AiException('Името е задължително (до 100 знака).');
        }

        $data = [
            'name' => $name,
            'tags' => mb_substr(trim($tags), 0, 200),
            'source' => $source,
            'sourceUrl' => $sourceUrl,
            'entry' => $entry,
            'html' => $html,
            'summary' => mb_substr($summary, 0, ReferenceFetcher::MAX_SUMMARY_CHARS),
            'hasPackage' => $hasPackage ? 1 : 0,
            'userId' => $userId,
            'created' => date('Y-m-d H:i:s'),
        ];
        $this->db->execute(Sql::insert('AiUiTemplates', array_keys($data)), array_values($data));

        return $this->db->lastInsertId();
    }

    /** Допълва записа на пакет след разархивирането: главния документ, неговия HTML и обобщението. */
    public function finalize(int $id, string $entry, string $html, string $summary): void
    {
        $this->db->execute(
            'UPDATE `AiUiTemplates` SET `entry` = ?, `html` = ?, `summary` = ? WHERE `id` = ?',
            [$entry, $html, mb_substr($summary, 0, ReferenceFetcher::MAX_SUMMARY_CHARS), $id],
        );
    }

    /** Презаписва HTML-а на шаблона; предишният остава за едно връщане назад. */
    public function replaceHtml(int $id, string $html): void
    {
        $row = $this->db->fetchRow('SELECT `html` FROM `AiUiTemplates` WHERE `id` = ?', [$id]);
        if ($row === null || $row['html'] === null) {
            throw new AiException('Шаблонът няма HTML.');
        }

        $this->db->execute('UPDATE `AiUiTemplates` SET `previousHtml` = ?, `html` = ? WHERE `id` = ?', [(string) $row['html'], $html, $id]);
    }

    /** Разменя текущия и предишния HTML (второ натискане връща промяната). */
    public function swapPrevious(int $id): void
    {
        $row = $this->db->fetchRow('SELECT `html`, `previousHtml` FROM `AiUiTemplates` WHERE `id` = ?', [$id]);
        if ($row === null || $row['previousHtml'] === null) {
            throw new AiException('Няма предишна версия за връщане.');
        }

        $this->db->execute('UPDATE `AiUiTemplates` SET `html` = ?, `previousHtml` = ? WHERE `id` = ?', [(string) $row['previousHtml'], (string) $row['html'], $id]);
    }

    public function setSummary(int $id, string $summary): void
    {
        $this->db->execute('UPDATE `AiUiTemplates` SET `summary` = ? WHERE `id` = ?', [mb_substr($summary, 0, ReferenceFetcher::MAX_SUMMARY_CHARS), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiUiTemplates` WHERE `id` = ?', [$id]);
        self::removeDir($this->packageDir($id));
    }

    /** Папката на пакета; $id е цяло число, така че пътят не може да излезе от $packageRoot. */
    public function packageDir(int $id): string
    {
        return rtrim($this->packageRoot, '/\\') . '/' . $id;
    }

    /** @return list<string> относителните пътища на файловете в пакета */
    public function files(int $id): array
    {
        $root = realpath($this->packageDir($id));
        if ($root === false) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files);

        return $files;
    }

    /** Чете файл от пакета по относителен път; null, ако го няма или пътят е опасен. */
    public function read(int $id, string $relative): ?string
    {
        $relative = PackageImporter::cleanPath($relative);
        $path = $relative === null ? false : realpath($this->packageDir($id) . '/' . $relative);
        $root = realpath($this->packageDir($id));
        if ($path === false || $root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
