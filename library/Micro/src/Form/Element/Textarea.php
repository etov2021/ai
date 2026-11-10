<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Textarea extends Element
{
    public function render()
    {
        $tmp = '';

        $tmp .= '<textarea name="' . $this->getFullyName() . '" data-required="' . ($this->isRequired() ? 1 : 0) . '"' . $this->htmlAttributes() . $this->getClientValidate() . '>' . \escape($this->value) . '</textarea>';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }
}