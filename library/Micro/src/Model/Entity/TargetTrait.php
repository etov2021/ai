<?php

namespace Micro\Model\Entity;

trait TargetTrait
{
    public function getTargetId()
    {
        return $this->getId();
    }

    public function getTarget()
    {
        $parts = \explode('\\', \get_class($this));

        return $parts[0] . '_' . $parts[\count($parts) - 1];
    }
}