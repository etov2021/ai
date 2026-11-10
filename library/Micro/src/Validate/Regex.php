<?php

namespace Micro\Validate;

class Regex extends ValidateAbstract implements ClientValidateInterface
{
    protected $pattern;
    protected $flags;
    protected $fullPattern = false;

    protected $templates = [
        self::ERROR => 'Невалидна стойност',
    ];

    public function setFullPattern($fullPattern)
    {
        $this->fullPattern = $fullPattern ? true : false;
    }

    public function getFullPattern()
    {
        return $this->fullPattern;
    }

    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function setFlags($flags)
    {
        $this->flags = $flags;
    }

    public function isValid($value)
    {
        if (!\is_scalar($value)) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        if ($this->pattern === \null) {
            return \true;
        }

        if ($this->fullPattern) {
            $status = @preg_match($this->pattern, $value);
        } else {
            $status = @preg_match('/' . $this->pattern . '/' . ($this->flags ?? ''), $value);
        }

        if ($status === 0 || $status === \false) {
            $this->messages[] = $this->templates[self::ERROR];
            return \false;
        }

        return \true;
    }

    public function toClient()
    {
        return [
            'name' => 'regex',
            'message' => $this->templates[self::ERROR],
            'options' => [
                'pattern' => $this->pattern,
                'flags' => $this->flags,
            ],
        ];
    }
}
