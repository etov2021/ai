<?php

namespace Micro\Helper;

use Micro\Application\Module;
use Micro\Container\SharedContainer;
use Micro\View\Widget\WidgetInterface;
use Micro\Application\Utils;
use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;

class Files
{
    public static function fetchWidgets($dirs = \null)
    {
        $dirs = SharedContainer::getInstance()->get('config')->get('modules', []);
        $data = [];

        foreach ($dirs as $m => $dir) {

            if ($dir instanceof Module) {
                $dir = $dir->getDir();
            } else {
                $dir = \realpath($dir);
            }

            if ($dir === false) {
                continue;
            }

            $files = Utils::globRecursive($dir . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . '*.php');

            foreach ($files as $file) {
                $pathname = \trim(\str_replace($dir, '', realpath($file)), DIRECTORY_SEPARATOR); // remove dir
                $pathname = \substr($pathname, 0, -4); // remove php
                $pathname = \str_replace(['/', '\\'], '\\', $pathname); // fix path to namespace
                $class    = $m . '\\' . $pathname;
                if ($class) {
                    try {
                        $ref = new \ReflectionClass($class);
                        if ($ref && $ref->isSubclassOf(WidgetInterface::class)) {
                            $data[$ref->name] = [];
                            foreach ($ref->getMethod('__invoke')->getParameters() as $parameter) {
                                $data[$ref->name][$parameter->name] = [
                                    'hasValue' => $parameter->isDefaultValueAvailable(),
                                    'defaultValue' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : \null,
                                    'value' => $parameter->isDefaultValueAvailable() ? htmlspecialchars($parameter->getDefaultValue()) : \null,
                                ];
                            }
                        }
                    } catch (\Throwable $e) {

                    }
                }
            }
        }

        return $data;
    }

    public static function fetchResources($dirs = \null)
    {
        $dirs = SharedContainer::getInstance()->get('config')->get('modules', []);

        $data = [];

        foreach ($dirs as $m => $dir) {

            if ($dir instanceof Module) {
                $dir = $dir->getDir();
            } else {
                $dir = \realpath($dir);
            }

            if ($dir === \false) {
                continue;
            }

            $files = Utils::globRecursive($dir . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . '*.php');

            foreach ($files as $file) {
                $pathname = \trim(\str_replace($dir, '', realpath($file)), DIRECTORY_SEPARATOR); // remove dir
                $pathname = \substr($pathname, 0, -4); // remove php
                $pathname = \str_replace(['/', '\\'], '\\', $pathname); // fix path to namespace
                $class    = $m . '\\' . $pathname;
                if ($class) {
                    try {
                        $ref = new \ReflectionClass($class);
                        if ($ref && $ref->isSubclassOf(Controller::class)) {
                            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                $name = $method->name;
                                if ('Action' !== \substr($name, -6)) {
                                    continue;
                                }
                                $actionDocBlock = static::parseDocBlock($method->getDocComment());
                                if (!\array_key_exists('NotAuthorize', $actionDocBlock)
                                    && (\array_key_exists('AuthorizeMethod', $actionDocBlock) || $ref->isSubclassOf(AuthorizeInterface::class))
                                ) {
                                    $data[$ref->name . '@' . \substr($name, 0, -6)] = $actionDocBlock;
                                }
                            }
                        }
                    } catch (\Throwable $e) {

                    }
                }
            }

            /* $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo) {
                    if ($f->isFile() && $f->getExtension() === 'php') {
                        $pathname = $f->getPathname();
                        if (\preg_match('~' . preg_quote($m . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR) . '~', $pathname)) {
                            $pathname = \trim(\str_replace($dir, '', $pathname), DIRECTORY_SEPARATOR);
                            $pathname = \substr($pathname, 0, -4);
                            $pathname = \str_replace(DIRECTORY_SEPARATOR, '\\', $pathname);
                            $class    = $m . '\\' . $pathname;
                            if ($class) {
                                try {
                                    $ref = new \ReflectionClass($class);
                                    foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                        $name = $method->name;
                                        if ('Action' !== \substr($name, -6)) {
                                            continue;
                                        }
                                        $data[$ref->name . '@' . \substr($name, 0, -6)] = static::parseDocBlock($method->getDocComment());
                                    }
                                } catch (\Throwable $e) {

                                }
                            }
                        }
                    }
                }
            } */
        }

        $tree = [];

        foreach ($data as $resource => $doc) {
            $resource = \str_replace(array("\\", "@"), "_", $resource);
            static::buildResourcesTree(\explode('_', $resource), $tree, \null, $resource, $doc);
        }

        return $tree;
    }

    public static function buildResourcesTree(array $parts, &$tree, $parent, $resource = \null, ?array $info = \null)
    {
        $part = \array_shift($parts);

        if ($part === \null) {
            return;
        }

        if ($parent === \null) {
            $parent = $part;
        } else {
            $parent = $parent . '_' . $part;
        }

        if (!isset($tree[$part])) {

            $name = $part;

            if ($info !== \null && $resource === $parent && isset($info['translate'])) {
                $name = $info['translate'];
            }

            $tree[$part] = [
                'id'        => $parent,
                'name'      => $name,
                'resources' => []
            ];
        }

        static::buildResourcesTree($parts, $tree[$part]['resources'], $parent, $resource, $info);
    }

    public static function parseDocBlock($docComment)
    {
        $lines = [];

        // First remove doc block line starters
        $docComment = \preg_replace('#[ \t]*(?:\/\*\*|\*\/|\*)?[ ]{0,1}(.*)?#', '$1', $docComment);
        $docComment = \ltrim($docComment, "\r\n");

        // Next parse out the tags and descriptions
        $parsedDocComment = $docComment;
        $lineNumber = $firstBlandLineEncountered = 0;

        while (($newlinePos = \strpos($parsedDocComment, "\n")) !== \false) {

            $lineNumber++;

            $line = \substr($parsedDocComment, 0, $newlinePos);

            if ((\strpos($line, '@') === 0) && (\preg_match('#^(@\w+.*?)(\n)(?:@|\r?\n|$)#s', $parsedDocComment, $matches))) {
                if (\preg_match('#^@(\w+)(?:\s+([^\s].*)|$)?#', $matches[1], $submatches)) {
                    $lines[$submatches[1]] = isset($submatches[2]) ? $submatches[2] : \null;
                }
                $parsedDocComment = \str_replace($matches[1] . $matches[2], '', $parsedDocComment);
            } else {
                if ($line == '') {
                    $firstBlandLineEncountered = \true;
                }
                $parsedDocComment = \substr($parsedDocComment, $newlinePos + 1);
            }
        }

        return $lines;
    }

    public static function fetchFromFiles($dirs, callable $callback)
    {
        if ($dirs === \null) {
            $dirs = SharedContainer::getInstance()->get('config')->get('modules', []);
        }

        $data = [];

        foreach ($dirs as $dir) {

            if ($dir instanceof Module) {
                $dir = $dir->getDir();
            } else {
                $dir = \realpath($dir);
            }

            if ($dir === \false) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($it as $f) {

                if ($f instanceof \SplFileInfo) {

                    if ($f->isFile() && $f->getExtension() === 'php') {

                        $class = static::getClassFromFile($f->getPathname());

                        if ($class) {
                            try {
                                $callback(new \ReflectionClass($class), $data);
                            } catch (\Throwable $e) {

                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    public static function getClassFromFile($path_to_file)
    {
        //Grab the contents of the file
        $contents = file_get_contents($path_to_file);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = \false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            if (is_array($token) && $token[0] == T_TRAIT) {
                return '';
            }

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = \true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS) {
                $getting_class = \true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === \true) {

                //If the token is a string or the namespace separator...
                if(is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];

                }
                else if ($token === ';') {

                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = \false;

                }
            }

            //While we're grabbing the class name...
            if ($getting_class === \true) {

                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Got what we need, stope here
                    break;
                }
            }
        }

        //Build the fully-qualified class name and return it
        return $namespace && $class ? $namespace . '\\' . $class : $class;
    }

    public static function getDocClass(\ReflectionClass $class, $recursive = \true)
    {
        $result = [];

        if ($recursive && $class->getParentClass()) {
            $result = static::getDocClass($class->getParentClass(), $recursive);
        }

        $doc = $class->getDocComment();

        if ($doc) {
            $result = \array_merge($result, static::parseDocBlock($doc));
        }

        return $result;
    }
}