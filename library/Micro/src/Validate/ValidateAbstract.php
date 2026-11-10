<?php

namespace Micro\Validate;

abstract class ValidateAbstract implements ValidateInterface
{
    const ERROR = 'error';

    protected $messages  = [];
    protected $templates = [];

    public function __construct(?array $options = \null)
    {
        if ($options !== \null) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        foreach ($options as $k => $v) {
            $method = 'set' . \ucfirst($k);
            if (\method_exists($this, $method)) {
                $this->$method($v);
            }
        }
    }

    public function setTemplates(array $templates)
    {
        foreach ($templates as $key => $value) {
            $this->templates[$key] = $value;
        }
    }

    public function getTemplates()
    {
        return $this->templates;
    }

    public function setError($value)
    {
        $this->templates[static::ERROR] = $value;
    }

    public function isValid($value)
    {
        return \true;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}