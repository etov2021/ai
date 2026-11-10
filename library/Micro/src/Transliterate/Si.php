<?php

namespace Micro\Transliterate;

class Si extends TransliterateAbstract
{
    protected $map = [];

    protected $mapReverse = [];

    public function convert($text, $forward = \true)
    {
        return parent::_convert($text, $forward);
    }
}