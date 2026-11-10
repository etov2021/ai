<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Hidden extends Element
{
    public function render()
    {
        $tmp = '';

        $tmp .= '<input type="hidden" name="' . $this->getFullyName() . '" value="' . \escape($this->value) . '"' . $this->htmlAttributes() . ' />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }
}