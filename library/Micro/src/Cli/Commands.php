<?php

namespace Micro\Cli;

class Commands
{
    public static $commands = [];

    public static function run(array $options)
    {
        foreach ($options as $k => $v) {
            $i = 0;
            while (isset(static::$commands[($k = static::execute($k, $options, $i ? true : false))])) {
                if (++$i >= 10) {
                    break;
                }
            }
        }
    }

    public static function execute($command, array $options, $forked = false)
    {
        if (!isset(static::$commands[$command])) {
            return null;
        }

        return call_user_func(static::$commands[$command], $options, $forked);
    }

    public static function add($name, callable $callback)
    {
        if (isset(static::$commands[$name])) {
            throw new \Exception(sprintf('Command "%s" exists', $name));
        }

        static::$commands[$name] = $callback;
    }
}