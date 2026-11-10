<?php

namespace Nomenclatures\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class StatusInner extends EntityAbstract implements TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $name;
    protected $color;
    protected $active = 1;
    protected $order;
    
    /**
     * @return the $order
     */
    public function getOrder()
    {
        return $this->order;
    }
    
    /**
     * @param number $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }
}