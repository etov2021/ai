<?php

namespace Micro\Validate;

interface ValidateInterface
{
    public function isValid($value);

    public function getMessages();
}