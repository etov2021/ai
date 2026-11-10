<?php

namespace Micro\Validate;

class Phone extends Regex
{
    protected $pattern = "^[\-\+\*\# 0-9]+$";
}