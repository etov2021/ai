<?php

namespace Micro\Form\Element;

class Button extends Submit
{
    public function render()
    {
        $tmp = '';

        $tmp .= '<button name="' . $this->getFullyName() . '"' . $this->htmlAttributes() . '>' . \escape($this->translate ? \translate($this->value) : $this->value) . '</button>';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }
}