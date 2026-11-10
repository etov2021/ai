<?php

namespace Micro\Assets;

class File
{
    public $file;
    public $options = [];

    public function __construct($file, array $options = [])
    {
        $this->file = $file;
        $this->options = $options;
    }

    public static function create($file, array $options = [])
    {
        return new self($file, $options);
    }

    public function __toString()
    {
        return $this->file;
    }

    public static function __set_state($data)
    {
        $o = new File($data['file'], $data['options']);
        return $o;
    }
}