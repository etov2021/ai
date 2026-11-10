<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Submit extends Element
{
    public function render()
    {
        $tmp = '';

        $tmp .= '<input type="submit" name="' . $this->getFullyName() . '" value="' . \escape($this->translate ? \translate($this->value) : $this->value) . '"' . $this->htmlAttributes() . ' />';

        if ($this->showErrors == \true) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function setValue($value)
    {
        if ($value !== \null) {
            $this->value = $value;
        }

        return $this;
    }
}