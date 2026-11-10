<?php

namespace Micro\Database\Statement;

use Micro\Database\Exception as DatabaseException;

class Exception extends DatabaseException
{
    public function __construct($message, $code = 0, ?\Throwable $previous = null)
    {
        return parent::__construct($message, (int) $code, $previous);
    }
}