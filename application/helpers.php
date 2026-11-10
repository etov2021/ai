<?php

use Micro\Translator\Language\LanguageInterface;
use App\Table\Settings;

if (!function_exists('get_param'))
{
    function get_param($key, $default = null)
    {
        return app('request')->getParam($key, $default);
    }
}

if (!function_exists('human_filesize'))
{
    function human_filesize($bytes, $decimals = 2)
    {
        $size = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];

        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}

if (!function_exists('settings'))
{
    function settings($key, $default = null, $domainId = null)
    {
        return Settings::get($key, $default, $domainId);
    }
}

if (!function_exists('render_flag'))
{
    function render_flag($name = true, ?LanguageInterface $language = null)
    {
        static $flag;

        if ($flag === null) {
            $language = $language ?: app('current_language', true);
            if ($language instanceof LanguageInterface) {
                $flag = '<img src="templates/admin/images/blank.gif" class="flag flag-' . $language->getLocale() . '" alt="' . $language->getName() . '">';
                if ($name) {
                    $flag .= ' ' . $language->getName();
                }
            } else {
                $flag = '';
            }
        }

        return $flag;
    }
}

if (!function_exists('send_mail'))
{
    function send_mail($alias, $to, array $data = [], $from = null, $language = null, $options = [])
    {
        return (new Email\Model\Mailer())->send($alias, $to, $data, $from, $language, $options);
    }
}

if (!function_exists('human_time_diff'))
{
    function human_time_diff($from, $to = 0)
    {
        $minuteInSeconds = 60;
        $hourInSeconds = $minuteInSeconds * 60;
        $dayInSeconds = 24 * $hourInSeconds;
        $weekInSeconds = 7 * $dayInSeconds;
        $monthInSeconds = 30 * $dayInSeconds;

        if (empty($to)) {
            $to = time();
        }

        $diff = (int) abs($to - $from);

        $since = '';

        if ($diff < $minuteInSeconds) {
            $secs = $diff;
            if ($secs <= 1) {
                $secs = 1;
            }
            $since = sprintf(translate($secs == 1 ? '%s секунда' : '%s секунди'), $secs);
        } elseif ($diff < $hourInSeconds && $diff >= $minuteInSeconds) {
            $mins = round($diff / $minuteInSeconds);
            if ($mins <= 1) {
                $mins = 1;
            }
            $since = sprintf(translate($mins == 1 ? '%s минута' : '%s минути'), $mins);
        } elseif ($diff < $dayInSeconds && $diff >= $hourInSeconds) {
            $hours = round($diff / $hourInSeconds);
            if ($hours <= 1) {
                $hours = 1;
            }
            $since = sprintf(translate($hours == 1 ? '%s час' : '%s часа'), $hours);
        } elseif ($diff < $weekInSeconds && $diff >= $dayInSeconds) {
            $days = round($diff / $dayInSeconds);
            if ($days <= 1) {
                $days = 1;
            }
            $since = sprintf(translate($days == 1 ? '%s ден' : '%s дена'), $days);
        }/*  elseif ($diff < $monthInSeconds && $diff >= $weekInSeconds) {
            $weeks = round($diff / $weekInSeconds);
            if ($weeks <= 1) {
                $weeks = 1;
            }
            $since = sprintf(translate($weeks == 1 ? '%s седмица' : '%s седмици'), $weeks);
        } */

        if ($since) {
            $since = translate('преди') . ' ' . $since;
        }

        return $since;
    }
}

if (!function_exists('get_formated_price'))
{
    function get_formated_price($value, $default = '')
    {
        if (empty($value)) {
            return $default;
        }

        $value = preg_replace('~[^0-9\,\.]~', '', $value);
        $value = str_replace(',', '.', $value);

        return number_format($value, 0, '.', ' ') . ' ' . translate('лв.');
    }
}