<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class Datetime extends Element
{
    protected $format = 'Y-m-d';

    public function getValue()
    {
        if ($this->value) {
            try {
                return new \DateTime($this->value);
            } catch (\Throwable $e) {
                return '';
            }
        }

        return $this->value;
    }

    public function render()
    {
        $value = '';

        try {

            if (($elValue = $this->getValue()) instanceof \DateTime) {
                $value = $elValue->format($this->format);
            }

        } catch (\Throwable $e) {
            if (is_debug()) {
                $value = __METHOD__ . ' :: ' . $e->getMessage();
            }
        }

        $tmp = '<input type="text" name="' . $this->getFullyName() . '" value="' . \escape($value) . '"' . $this->htmlAttributes() . ' />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function setFormat($value)
    {
        $this->format = $value;
    }

    public function getFormat()
    {
        return $this->format;
    }
}