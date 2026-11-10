<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Text extends Element
{
    protected $type = 'text';

    public function render()
    {
        $tmp = '';

        $tmp .= '<input type="' . $this->type . '" name="' . $this->getFullyName() . '" data-required="' . ($this->isRequired() ? 1 : 0) . '" value="' . \escape($this->value) . '"' . $this->htmlAttributes() . $this->getClientValidate() . ' />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }
}