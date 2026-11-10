<?php

namespace Micro\Transliterate;

class Sl extends TransliterateAbstract
{
    protected $map = [];

    protected $mapReverse = [];

    public function convert($text, $forward = \true)
    {
        return parent::_convert($text, $forward);
    }
}