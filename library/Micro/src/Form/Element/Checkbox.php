<?php

namespace Micro\Form\Element;

use Micro\Form\Element;
use Micro\Validate;

class Checkbox extends Element
{
    protected $checkedValue = '1';
    protected $uncheckedValue = '0';

    public function isValid($value, ?array $context = \null)
    {
        if ($this->isRequired() && !isset($this->validators[Validate\NotIdentical::class])) {
            $this->prependValidator(new Validate\NotIdentical([
                'value' => $this->uncheckedValue,
                'error' => 'Полето е задължително'
            ]));
        }

        if ($value === null) {
            $value = $this->uncheckedValue;
        }

        return parent::isValid($value, $context);
    }

    public function render()
    {
        $tmp = '';

        $checked = '';

        if ($this->isChecked()) {
            $checked = ' checked="checked"';
        }

        $name = $this->getFullyName();

        $tmp .= '<input type="hidden" name="' . $name . '" value="' . \escape($this->uncheckedValue) . '" />';
        $tmp .= '<input type="checkbox" name="' . $name . '" value="' . \escape($this->checkedValue) . '" data-required="' . ($this->isRequired() ? 1 : 0) . '"' . $checked . $this->htmlAttributes() . $this->getClientValidate() . ' />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function setCheckedValue($value)
    {
        $this->checkedValue = $value;

        return $this;
    }

    public function setUnCheckedValue($value)
    {
        $this->uncheckedValue = $value;

        return $this;
    }

    public function isChecked()
    {
        return $this->value == $this->checkedValue;
    }
}