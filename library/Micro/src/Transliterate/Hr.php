<?php

namespace Micro\Transliterate;

class Hr extends TransliterateAbstract
{
    protected $map = [
        'a' => 'a',
        'b' => 'b',
        'c' => 'c',
        'c' => 'č',
        'c' => 'ć',
        'd' => 'd',
        'dz' => 'dž',
        'dj' => 'đ',
        'e' => 'e',
        'f' => 'f',
        'g' => 'g',
        'h' => 'h',
        'i' => 'i',
        'j' => 'j',
        'k' => 'k',
        'l' => 'l',
        'l' => 'lj',
        'm' => 'm',
        'n' => 'n',
        'n' => 'nj',
        'o' => 'o',
        'p' => 'p',
        'r' => 'r',
        's' => 's',
        's' => 'š',
        't' => 't',
        'u' => 'u',
        'v' => 'v',
        'z' => 'z',
        'z' => 'ž',
    ];

    protected $mapReverse = [
        'a' => 'a',
        'b' => 'b',
        'c' => 'c',
        'č' => 'c',
        'ć' => 'c',
        'd' => 'd',
        'dž' => 'dz',
        'đ' => 'dj',
        'e' => 'e',
        'f' => 'f',
        'g' => 'g',
        'h' => 'h',
        'i' => 'i',
        'j' => 'j',
        'k' => 'k',
        'l' => 'l',
        'lj' => 'l',
        'm' => 'm',
        'n' => 'n',
        'nj' => 'n',
        'o' => 'o',
        'p' => 'p',
        'r' => 'r',
        's' => 's',
        'š' => 's',
        't' => 't',
        'u' => 'u',
        'v' => 'v',
        'z' => 'z',
        'ž' => 'z',
    ];

    public function convert($text, $forward = \true)
    {
        $text = mb_strtolower($text, 'UTF-8');

        return parent::_convert($text, $forward);
    }
}