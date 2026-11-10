<?php

namespace Micro\Grid\Column;

use Micro\Grid\Column;

class Boolean extends Column
{
    protected $true  = 1;
    protected $false = 0;

    public function setTrue($true)
    {
        $this->true = $true;
    }

    public function setFalse($false)
    {
        $this->false = $false;
    }

    public function render()
    {
        $value = (bool) parent::render();

        if ($value) {
            return (string) $this->true;
        }

        return (string) $this->false;
    }
}