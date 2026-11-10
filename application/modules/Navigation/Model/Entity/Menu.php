<?php

namespace Navigation\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Entity\DomainTrait;

class Menu extends EntityAbstract implements DomainInterface
{
    use DomainTrait;

    protected $id;
    protected $name;
    protected $alias;
    protected $active = 1;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($active)
    {
        $this->active = $active;
    }


}