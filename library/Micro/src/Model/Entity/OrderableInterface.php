<?php

namespace Micro\Model\Entity;

interface OrderableInterface
{
    public function getOrder();
    public function setOrder($order);
}