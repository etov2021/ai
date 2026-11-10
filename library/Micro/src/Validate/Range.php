<?php

namespace Micro\Validate;

use Micro\Validate\ValidateAbstract;

class Range extends ValidateAbstract implements ClientValidateInterface
{
    const ERROR_RANGE = 'range';
    const ERROR_MIN = 'min';
    const ERROR_MAX = 'max';

    protected $templates = [
        self::ERROR_RANGE => 'Моля, въведете стойност между %s и %s',
        self::ERROR_MIN => 'Моля, въведете стойност по-голяма или равна на %s',
        self::ERROR_MAX => 'Моля, въведете стойност по-малка или равна на %s',
    ];

    protected $min;
    protected $max;

    public function isValid($value)
    {
        if (is_array($value)) {
            $value = implode('', $value);
        }

        if ($this->min !== null && $this->max !== null) {
            if ($value >= $this->min && $value <= $this->max) {
                return true;
            }
            $this->messages[] = [$this->templates[self::ERROR_RANGE], $this->min, $this->max];
        } else if ($this->min !== null) {
            if ($value>= $this->min) {
                return true;
            }
            $this->messages[] = [$this->templates[self::ERROR_MIN], $this->min];
        } else if ($this->max !== null) {
            if ($value <= $this->max) {
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
            'name' => 'range',
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