<?php

namespace Micro\Validate;

class Url extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        self::ERROR => 'Невалиден адрес'
    ];

    public function isValid($value)
    {
        if (\filter_var($value, FILTER_VALIDATE_URL) === \false) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }

    public function toClient()
    {
        return [
            'name' => 'url',
            'message' => $this->templates[self::ERROR],
        ];
    }
}
