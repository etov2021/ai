<?php

namespace Micro\Validate;

class InArray extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        self::ERROR => 'Стойността не съществува'
    ];

    protected $haystack;

    public function setHaystack($haystack)
    {
        $this->haystack = $haystack;
    }

    public function isValid($value, $context = \null)
    {
        if (!\is_array($this->haystack)) {
            return \true;
        }

        if (!\is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $v) {
            if (!\array_key_exists($v, $this->haystack)) {
                $this->messages[] = $this->templates[self::ERROR];
                return \false;
            }
        }

        return \true;
    }

    public function toClient()
    {
        return [
            'name' => 'inArray',
            'message' => $this->templates[self::ERROR],
            'options' => [
                'haystack' => (is_array($this->haystack) ? $this->haystack : []),
            ],
        ];
    }
}
