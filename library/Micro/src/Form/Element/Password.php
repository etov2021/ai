<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Password extends Element
{
    protected $showValue = false;

    public function render()
    {
        $tmp = '';

        $value = '';

        if ($this->showValue) {
            $value = \escape($this->value);
        }

        $tmp .= '<input type="password" name="' . $this->getFullyName() . '" value="' . $value . '"' . $this->htmlAttributes() . ' />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function setShowValue($value)
    {
        $this->showValue = (bool) $value;

        return $this;
    }
}