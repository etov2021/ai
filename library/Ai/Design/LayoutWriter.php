<?php

declare(strict_types=1);

namespace Ai\Design;

use Ai\Exception\AiException;

/**
 * Единственото място, където дизайнерът пише във файловата система на приложението:
 * layouts/<име>/default.phtml и public/templates/<име>/. Админът (layouts/admin, templates/admin) не се пипа.
 */
final class LayoutWriter
{
    public const ASSET_MARK = '.aiimpl-ui-assets';
    private const RESERVED = ['admin', 'shared', 'layouts', 'old', 'frontend', 'libs', 'css', 'js', 'images', 'fonts', 'includes'];

    /**
     * @param string $layoutsRoot application/resources/layouts
     * @param string $publicRoot public (оттук се четат вграденият Bootstrap и се пише в templates/<име>/)
     */
    public function __construct(private string $layoutsRoot, private string $publicRoot)
    {
    }

    /** @throws AiException */
    public static function assertName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{1,39}$/', $name) !== 1) {
            throw new AiException('Името е 2-40 знака: малки латински букви, цифри и тире, започва с буква.');
        }
        if (in_array($name, self::RESERVED, true)) {
            throw new AiException('Името "' . $name . '" е запазено. Изберете друго (напр. site).');
        }
    }

    /** @return string пътят на файла, относителен спрямо layouts */
    public function write(string $name, string $code, bool $overwrite): string
    {
        $directory = $this->assertWritable($name, $overwrite);
        $file = $directory . DIRECTORY_SEPARATOR . 'default.phtml';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new AiException('Папката layouts/' . $name . ' не може да бъде създадена.');
        }

        $temporary = $file . '.tmp' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $code, LOCK_EX) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new AiException('Layout не може да бъде записан.');
        }

        return $name . '/default.phtml';
    }

    /**
     * Проверява дали layout-ът може да се запише, без да пише нищо.
     *
     * @return string пълният път на папката на layout-а
     * @throws AiException
     */
    public function assertWritable(string $name, bool $overwrite): string
    {
        self::assertName($name);

        $root = realpath($this->layoutsRoot);
        if ($root === false) {
            throw new AiException('Папката за layouts не е намерена.');
        }
        $directory = $root . DIRECTORY_SEPARATOR . $name;
        $file = $directory . DIRECTORY_SEPARATOR . 'default.phtml';

        if (is_dir($directory) || file_exists($file)) {
            // Чужд layout (напр. ръчно написан) никога не се презаписва, дори с избрано „презапиши“.
            if (!is_file($file) || !str_contains((string) file_get_contents($file, false, null, 0, 160), LayoutBuilder::MARK)) {
                throw new AiException('Папката layouts/' . $name . ' вече съществува и не е създадена от дизайнера.');
            }
            if (!$overwrite) {
                throw new AiException('Layout "' . $name . '" вече съществува. Отбележете „Презапиши“ или изберете друго име.');
            }
        }

        return $directory;
    }

    /**
     * Копира файловете на layout-а в public/templates/<име>/: статичните файлове на пакета и вграденият Bootstrap.
     *
     * @param ?string $packageDir папка с файловете на пакета (null за макет без пакет)
     * @param list<string> $packageFiles относителни пътища в пакета
     * @param array<string, string> $vendor файл от public => път в public/templates/<име>/
     * @param array<string, string> $generated изнесените от HTML css/js файлове: път в public/templates/<име>/ => съдържание
     * @return int брой копирани файлове
     * @throws AiException
     */
    public function copyAssets(string $name, ?string $packageDir, array $packageFiles, array $vendor, bool $overwrite, array $generated = []): int
    {
        self::assertName($name);

        $templates = realpath($this->publicRoot . DIRECTORY_SEPARATOR . 'templates');
        if ($templates === false) {
            throw new AiException('Папката public/templates не е намерена.');
        }
        $target = $templates . DIRECTORY_SEPARATOR . $name;
        $mark = $target . DIRECTORY_SEPARATOR . self::ASSET_MARK;

        if (is_dir($target) && !is_file($mark)) {
            throw new AiException('Папката public/templates/' . $name . ' вече съществува и не е създадена от дизайнера.');
        }
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new AiException('Папката public/templates/' . $name . ' не може да бъде създадена.');
        }
        file_put_contents($mark, 'Създадено от UI дизайнера. Файловете тук се презаписват при ново интегриране.');

        $copied = 0;
        $sources = [];
        $packageRoot = $packageDir === null ? false : realpath($packageDir);
        foreach ($packageFiles as $relative) {
            $clean = PackageImporter::cleanPath($relative);
            $extension = strtolower(pathinfo((string) $clean, PATHINFO_EXTENSION));
            // HTML страниците на шаблона не се копират: те не са част от layout-а.
            if ($clean !== null && $packageRoot !== false && in_array($extension, PackageImporter::ALLOWED, true) && !in_array($extension, ['html', 'htm'], true)) {
                $sources[$clean] = $packageRoot . DIRECTORY_SEPARATOR . $clean;
            }
        }
        foreach ($vendor as $source => $relative) {
            if (!in_array($source, [HtmlSanitizer::BOOTSTRAP_CSS, HtmlSanitizer::BOOTSTRAP_JS], true) || PackageImporter::cleanPath($relative) === null) {
                continue;
            }
            $sources[$relative] = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        }

        foreach ($sources as $relative => $from) {
            $real = realpath($from);
            $to = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($real === false || !is_file($real)) {
                continue;
            }
            if (file_exists($to) && !$overwrite) {
                throw new AiException('Файлът public/templates/' . $name . '/' . $relative . ' вече съществува. Отбележете „Презапиши“.');
            }
            if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) {
                throw new AiException('Папка в public/templates/' . $name . ' не може да бъде създадена.');
            }
            if (!copy($real, $to)) {
                throw new AiException('Файлът ' . $relative . ' не може да бъде копиран.');
            }
            $copied++;
        }
// Само css и js от фиксирани папки: съдържанието идва от HTML-а на шаблона и се записва като статичен файл.
        foreach ($generated as $relative => $content) {
            $clean = PackageImporter::cleanPath($relative);
            if ($clean === null || !in_array(strtolower(pathinfo($clean, PATHINFO_EXTENSION)), ['css', 'js'], true)) {
                continue;
            }
            $to = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
            if (file_exists($to) && !$overwrite) {
                throw new AiException('Файлът public/templates/' . $name . '/' . $clean . ' вече съществува. Отбележете „Презапиши“.');
            }
            if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) {
                throw new AiException('Папка в public/templates/' . $name . ' не може да бъде създадена.');
            }
            if (file_put_contents($to, $content, LOCK_EX) === false) {
                throw new AiException('Файлът ' . $clean . ' не може да бъде записан.');
            }
            $copied++;
        }

        
        return $copied;
    }
}
