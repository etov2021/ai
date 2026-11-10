<?php

namespace Micro\Validate;

class Email extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        self::ERROR => 'Невалидна електронна поща'
    ];

    public function isValid($value)
    {
        if (\filter_var($value, FILTER_VALIDATE_EMAIL) === \false) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }

    public function toClient()
    {
        return [
            'name' => 'email',
            'message' => $this->templates[self::ERROR],
        ];
    }
}
