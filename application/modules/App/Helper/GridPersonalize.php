<?php

namespace App\Helper;

class GridPersonalize
{
    public static function sessionGridReset($name)
    {
        session()->gridPersonalize[$name] = [];
    }

    public static function sessionGridColumnOption($name, $column, $option, $value = null, $default = null)
    {
        if ($value === null) {
            return session()->gridPersonalize[$name][$column][$option] ?? $default;
        }

        session()->gridPersonalize[$name][$column][$option] = $value;
    }

    public static function sessionGridOption($name, $option, $value = null, $default = null)
    {
        if ($value === null) {
            return session()->gridPersonalize[$name][$option] ?? $default;
        }

        session()->gridPersonalize[$name][$option] = $value;
    }
}