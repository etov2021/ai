<?php

namespace Micro\Http;

class TempStream extends Stream
{
    public function __construct($data = \null, $mode = 'r+')
    {
        $this->attach(fopen('php://temp', $mode));

        if (!empty($data)) {
            $this->write($data);
        }
    }
}
