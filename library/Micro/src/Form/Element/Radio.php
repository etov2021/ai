<?php

namespace Micro\Form\Element;

use Micro\Validate;
use Micro\Validate\InArray;

class Radio extends Select
{
    protected $renderNormal = 0;
    
    public function isValid($value, ?array $context = \null)
    {
        if ($this->isRequired() && !isset($this->validators[Validate\NotIdentical::class])) {
            $this->prependValidator(new Validate\NotIdentical([
                'value' => "",
                'error' => 'Полето е задължително'
            ]));
        }

        if ($this->valueExists && !isset($this->validators[InArray::class])) {
            $this->prependValidator(new InArray([
                'haystack' => $this->multiOptions,
            ]));
        }

        if ($value === null) {
            $value = "";
        }

        return parent::isValid($value, $context);
    }

    public function render()
    {
        $tmp = '';

        $name = $this->getFullyName();

        foreach ($this->multiOptions as $k => $v) {

            $tmp .= '<div class="radio radio-' . $this->getName() . '">';

            $checked = '';

            if ($this->value == $k) {
                $checked = ' checked="checked"';
            }

            if($this->renderNormal) {
                $tmp .= '<input id="' . $name . '-' . \escape($k) . '" type="radio" name="' . $name . '" value="' . \escape($k) . '" data-required="' . ($this->isRequired() ? 1 : 0) . '"' . $checked . $this->htmlAttributes() . $this->getClientValidate() . ' />';
                $tmp .= '<label for="' . $name . '-' . \escape($k) . '">' . \escape($this->translateValue ? \translate($v) : $v) . '</span>';
            } else {
                $tmp .= '<label>';
                $tmp .= '<input type="radio" name="' . $name . '" value="' . \escape($k) . '" data-required="' . ($this->isRequired() ? 1 : 0) . '"' . $checked . $this->htmlAttributes() . $this->getClientValidate() . ' />';
                $tmp .= '<span class="element-radio-label">' . \escape($this->translateValue ? \translate($v) : $v) . '</span>';
                $tmp .= '</label>';
            }
            

            $tmp .=  '</div>';
        }

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }
    public function setRenderNormal($value)
    {
        $this->renderNormal = $value;
        
        return $this;
    }
}