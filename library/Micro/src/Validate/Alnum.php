<?php

namespace Micro\Validate;

class Alnum extends Regex
{
    protected $pattern = '^[а-яА-Яa-zA-Z0-9 \.\,\_\-]+$';
}