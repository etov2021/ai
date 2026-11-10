<?php

namespace Micro\Validate;

class DateIsNotEarlier extends ValidateAbstract
{
    protected $templates = [
        self::ERROR => 'Крайната дата трябва да бъде след началната'
    ];

    protected $field;

    public function setField($value)
    {
        $this->field = $value;
    }

    public function isValid($value, $context = \null)
    {
        if (!isset($context[$this->field])) {
            throw new \Exception('There is no a element ' . $this->field . ' in the form');
        }
        if (!$value || !$context[$this->field]) {
            return true;
        }

        try {
            $dateStart = new \DateTime($context[$this->field]);
            $dateEnd = $value instanceof \DateTime ? $value : new \DateTime($value);
        } catch (\Throwable $e) {
            $this->messages[] = __METHOD__ . ' :: ' . $e->getMessage();
            return \false;
        }

        $diffInterval = $dateStart->diff($dateEnd);

        if ($diffInterval->invert) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }
}