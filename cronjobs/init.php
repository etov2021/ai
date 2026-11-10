<?php

use Micro\Container\SharedContainer;
use Micro\Database\Database;

chdir(dirname(__DIR__));

define('APP_ENV', 'cronjobs');

require 'application/helpers.php';

require 'library/Micro/autoload.php';

if (is_file($composer = 'vendor/autoload.php')) {
    require $composer;
}

MicroLoader::register();

if ((is_file($classes = 'data/classes.php')) === \true) {
    MicroLoader::setFiles(include $classes);
}

define('DS', DIRECTORY_SEPARATOR);
define('NL', PHP_SAPI == 'cli' ? "\n" : "<br />");

class App
{
    protected static $app;

    /**
     * @return Micro\Application\Application
     */
    public static function instance($name = null)
    {
        if (static::$app === null) {
            static::$app = new Micro\Application\Application(
                include 'application/container.php'
            );

        }

        return static::$app;

    }

    public static function get($service)
    {
        return static::instance()->getContainer()->get($service);
    }

    /**
     * @return Micro\Database\Adapter\AdapterAbstract
     */
    public static function db()
    {
        return static::get('db');
    }

    public static function start($name = null)
    {
        static $started = false;

        $app = static::instance($name);

        if ($started) {
            return;
        }

        $started = true;

        $app->boot()->marshalConfigKeys();
        $app->getContainer()->get('event')->trigger('Application.Start');
    }


    public static function getDomains()
    {
        $domains = static::db()->fetchAll('SELECT * FROM Domains');

        $d = [];
        foreach ($domains as $domain) {
            $d[$domain['id']] = $domain;
        }

        return $d;
    }

    public static function getLanguages()
    {
        $languages = SharedContainer::getInstance()->get('languages');

        $langs = [];
        foreach ($languages as $language) {
            $langs[$language->getId()] = $language;
        }

        return $langs;
    }
}

class Bench
{
    protected static $names = [];
    protected static $stack = [];

    static function start($name)
    {
        static::$names[$name] = microtime(true);

        static::$stack[] = $name;

        $gap = count(static::$stack) * 10;

        return sprintf(str_repeat('-', $gap) . ' [' .  date(DATE_RFC822) . '] ' . ucfirst($name) . ' started.' . NL);
    }

    static function stop()
    {
        $gap = count(static::$stack) * 10;

        $name = array_pop(static::$stack);

        return sprintf(str_repeat('-', $gap)  . ' [' .  date(DATE_RFC822) . '] ' . ucfirst($name) . ' ended for %f sec.' . NL, microtime(true) - static::$names[$name]);
    }

    static function flushBuffers()
    {
        @ob_flush();
        @flush();
    }

}

function _bulkInsert($table, array $data)
{
    $db = App::db();
    $qs = $db->getQuoteIdentifierSymbol();

    $sql = 'INSERT INTO ' . $table . ' (' . $qs . implode($qs . ', ' . $qs, array_keys($data[0])) . $qs . ') VALUES ';
    $values = '';

    foreach ($data as $row) {
        foreach ($row as $k => $v) {
            if ($v === null) {
                $row[$k] = 'NULL';
            } else {
                $row[$k] = $db->quote($v);
            }
        }
        $values .= '(' . implode(', ', $row) . '), ';
    }

    $sql .= $values;
    $sql = trim($sql, ', ');

    return $db->query($sql)->rowCount();
}

function progressBar($done, $total)
{
    $perc = floor(($done / $total) * 100);
    $left = 100 - $perc;
    toBrowser("Progress: " . $perc . '% - ' . $done . '/' . $total . "\r");
}

function toBrowser($tmp)
{
    print $tmp;

    if (PHP_SAPI !== 'cli') {
        ob_flush();
        flush();
    }
}
