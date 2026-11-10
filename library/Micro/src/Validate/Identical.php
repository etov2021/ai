<?php

namespace Micro\Validate;

class Identical extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        self::ERROR => 'Полетата не съвпадат'
    ];

    protected $field;
    protected $value;

    public function setField($value)
    {
        $this->field = $value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function isValid($value, $context = \null)
    {
        if ($this->field === \null && $this->value === \null) {
            return \true;
        }

        if ($this->value !== \null) {
            if ($value !== $this->value) {
                $this->messages[] = $this->templates[self::ERROR];
                return \false;
            }
            return \true;
        }

        if (!isset($context[$this->field])) {
            $context[$this->field] = '';
        }

        if ($value !== $context[$this->field]) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }

    public function toClient()
    {
        return [
            'name' => 'identical',
            'message' => $this->templates[self::ERROR],
            'options' => [
                'field' => $this->field,
            ],
        ];
    }
}
