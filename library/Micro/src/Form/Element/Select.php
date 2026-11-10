<?php

namespace Micro\Form\Element;

use Micro\Form\Element;
use Micro\Application\Utils;
use Micro\Validate\InArray;
use Micro\Validate\NotIdentical;

class Select extends Element
{
    protected $multiOptions = [];
    protected $valueExists = \false;
    protected $emptyOption;
    protected $emptyOptionValue = '';

    public function isValid($value, ?array $context = \null)
    {
        if ($this->isArray && $this->isRequired() && !isset($this->validators[NotIdentical::class])) {
            $this->prependValidator(new NotIdentical([
                'value' => [],
                'error' => 'Полето е задължително'
            ]));
        }

        if ($this->valueExists && !isset($this->validators[InArray::class])) {
            $this->prependValidator(new InArray([
                'haystack' => $this->multiOptions,
            ]));
        }

        if ($value === null) {
            if ($this->isArray) {
                $value = [];
            } else {
                $value = $this->emptyOptionValue;
            }
        }

        return parent::isValid($value, $context);
    }

    public function render()
    {
        if (empty($this)) {

        }

        $tmp = '';

        $name = $this->getFullyName(\false);

        if ($this->isArray) {
            $name .= '[]';
        }

        $options = Utils::buildOptions(
            $this->multiOptions,
            $this->value,
            (!empty($this->emptyOption) && $this->translate ? \translate($this->emptyOption) : $this->emptyOption),
            $this->emptyOptionValue,
            $this->translateValue
        );

        $tmp .= '<select' . ($this->isArray ? ' multiple="multiple"' : '') . ' name="' . $name . '" data-required="' . ($this->isRequired() ? 1 : 0) . '"' . $this->htmlAttributes() . $this->getClientValidate() . '>' . $options . '</select>';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function setValueExists($flag)
    {
        $this->valueExists = (bool) $flag;
    }


    public function setEmptyOption($value)
    {
        $this->emptyOption = $value;

        return $this;
    }

    public function setEmptyOptionvalue($value)
    {
        $this->emptyOptionValue = $value;

        return $this;
    }

    public function setMultiOptions($options)
    {
        $this->clearMultiOptions();

        $this->addMultiOptions($options);

        return $this;
    }

    public function getMultiOptions()
    {
        return $this->multiOptions;
    }

    public function getMultiOption($key, $default = null)
    {
        return isset($this->multiOptions[$key]) ? $this->multiOptions[$key] : $default;
    }

    public function clearMultiOptions()
    {
        $this->multiOptions = [];

        return $this;
    }

    public function addMultiOptions($options)
    {
        if (\is_callable($options)) {
            $options = \call_user_func($options);
        }

        if (\is_string($options) && \class_exists($options)) {
            $obj = new $options;
            if (\is_callable($obj)) {
                $options = \call_user_func($obj);
            }
        }

        if (!is_array($options)) {
            \trigger_error(__METHOD__ . ' -> $options must be array or callback', \E_USER_WARNING);
            $options = [];
        }

        foreach ($options as $k => $v) {
            $this->addMultiOption($k, $v);
        }

        return $this;
    }

    public function addMultiOption($k, $v)
    {
        $this->multiOptions[$k] = $v;

        return $this;
    }
}