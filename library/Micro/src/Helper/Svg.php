<?php

namespace Micro\Helper;

class Svg
{
    public static function extract($file, $version = null, $encoding = null)
    {
        $ids = [];

        if (is_file($file)) {
            $doc = new \DOMDocument($version, $encoding);
            $doc->load($file);
            $symbols = $doc->getElementsByTagName('symbol');
            foreach ($symbols as $symbol) {
                $id = $symbol->getAttribute('id');
                if ($id) {
                    $titles = $symbol->getElementsByTagName('title');
                    $ids[$id] = $titles->length ? $titles->item(0)->textContent : $id;
                }
            }
        }

        return $ids;
    }
}