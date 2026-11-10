<?php

namespace Micro\Application;

class Date extends \DateTime
{
    protected static $defaultFormat = 'Y-m-d H:i:s';

    public static function now($timezone = \null)
    {
        return new self(null, $timezone);
    }

    public static function getDefaultFormat()
    {
        return static::$defaultFormat;
    }

    public static function setDefaultFormat($format)
    {
        static::$defaultFormat = $format;
    }

    public function __toString()
    {
        return $this->format(static::$defaultFormat);
    }
}