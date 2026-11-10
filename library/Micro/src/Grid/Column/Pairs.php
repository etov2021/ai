<?php

namespace Micro\Grid\Column;

use Micro\Grid\Column;

class Pairs extends Column
{
    protected $pairs;
    protected $callable;
    protected $notFoundText = '';
    protected $params = [];

    public function setPairs(array $pairs)
    {
        $this->pairs = $pairs;
    }

    public function setCallable(callable $value)
    {
        $this->callable = $value;
    }

    public function setParams(array $value)
    {
        $this->params = $value;
    }

    public function setNotFoundText($notFoundText)
    {
        $this->notFoundText = $notFoundText;
    }

    public function render()
    {
        $value = $this->getCurrentValue();

        if ($this->pairs === \null) {
            if ($this->callable !== \null && \is_callable($this->callable)) {
                $this->pairs = \call_user_func_array($this->callable, $this->params);
            } else {
                $this->pairs = [];
            }
        }

        if (\is_scalar($value) && \preg_match('~\,~', $value)) {
            $value = explode(',', $value);
            $value = array_filter($value);
        }

        if (\is_array($value)) {
            $return = [];
            foreach ($value as $val) {
                if (isset($this->pairs[$val])) {
                    $return[] = ($this->translateValue ? \translate($this->pairs[$val]) : $this->pairs[$val]);
                }
            }
            if (!empty($return)) {
                return \implode(', ', $return);
            } else {
                return $this->notFoundText;
            }
        }

        return isset($this->pairs[$value])
               ? ($this->translateValue ? \translate($this->pairs[$value]) : $this->pairs[$value])
               : $this->notFoundText;
    }
}