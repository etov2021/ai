<?php

namespace Micro\Model\Entity;

interface EntityInterface extends \ArrayAccess
{
    public function setFromArray(array $data);

    public function toArray();

    public function toJson();
}