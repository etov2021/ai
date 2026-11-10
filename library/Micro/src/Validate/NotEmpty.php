<?php

namespace Micro\Validate;

class NotEmpty extends ValidateAbstract
{
    protected $templates = [
        self::ERROR => 'Полето е задължително'
    ];

    public function isValid($value)
    {
        if (('' === $value) || (\null === $value)) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }
}
