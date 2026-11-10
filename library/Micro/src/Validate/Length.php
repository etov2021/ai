<?php

namespace Micro\Validate;

use Micro\Validate\ValidateAbstract;

class Length extends ValidateAbstract implements ClientValidateInterface
{
    const ERROR_RANGE = 'range';
    const ERROR_MIN = 'min';
    const ERROR_MAX = 'max';

    protected $templates = [
        self::ERROR_RANGE => 'Моля, въведете стойност с дължина между %s и %s символа',
        self::ERROR_MIN => 'Моля, въведете поне %s символа',
        self::ERROR_MAX => 'Моля, въведете не повече от %s символа',
    ];

    protected $min;
    protected $max;

    public function isValid($value)
    {
        if (is_array($value)) {
            $length = count($value);
        } else {
            $length = mb_strlen((string) $value);
        }

        if ($this->min !== null && $this->max !== null) {
            if ($length >= (int) $this->min && $length <= (int) $this->max) {
                return true;
            }
            $this->messages[] = [$this->templates[self::ERROR_RANGE], $this->min, $this->max];
        } else if ($this->min !== null) {
            if ($length >= (int) $this->min) {
                return true;
            }
            $this->messages[] = [$this->templates[self::ERROR_MIN], $this->min];
        } else if ($this->max !== null) {
            if ($length <= (int) $this->max) {
                return true;
            }
            $this->messages[] = [$this->templates[self::ERROR_MAX], $this->max];
        }

        if (!empty($this->messages)) {
            return false;
        }

        return true;
    }

    public function setMin($min)
    {
        $this->min = $min;
    }

    public function setMax($max)
    {
        $this->max = $max;
    }

    public function toClient()
    {
        return [
            'name' => 'length',
            'message' => [
                self::ERROR_RANGE => $this->templates[self::ERROR_RANGE],
                self::ERROR_MIN => $this->templates[self::ERROR_MIN],
                self::ERROR_MAX => $this->templates[self::ERROR_MAX],
            ],
            'options' => [
                'min' => $this->min,
                'max' => $this->max,
            ],
        ];
    }
}