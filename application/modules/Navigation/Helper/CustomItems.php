<?php

namespace Navigation\Helper;

use Micro\Helper\Files;
use Navigation\Model\ItemsInterface;
use Micro\Container\SharedContainer;
use Micro\Application\Module;
use Micro\Application\Utils;

class CustomItems
{
    public static function fetch()
    {
        $dirs  = SharedContainer::getInstance()->get('config')->get('modules', []);
        $items = [];

        foreach ($dirs as $m => $dir) {

            if ($dir instanceof Module) {
                $dir = $dir->getDir();
            } else {
                $dir = \realpath($dir);
            }

            if ($dir === false) {
                continue;
            }

            $files = Utils::globRecursive($dir . DIRECTORY_SEPARATOR . 'Menu' . DIRECTORY_SEPARATOR . '*.php');

            foreach ($files as $file) {
                $pathname = \trim(\str_replace($dir, '', realpath($file)), DIRECTORY_SEPARATOR); // remove dir
                $pathname = \substr($pathname, 0, -4); // remove php
                $pathname = \str_replace(['/', '\\'], '\\', $pathname); // fix path to namespace
                $class    = $m . '\\' . $pathname;
                if ($class) {
                    $ref = new \ReflectionClass($class);
                    if ($ref && $ref->isSubclassOf(ItemsInterface::class)) {
                        $doc = Files::getDocClass($ref);
                        $items[$ref->name] = translate(isset($doc['translate']) ? $doc['translate'] : $ref->name);
                    }
                }
            }
        }

        asort($items);

        return $items;
    }
}