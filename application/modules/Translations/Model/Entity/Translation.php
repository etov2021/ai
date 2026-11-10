<?php

namespace Translations\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class Translation extends EntityAbstract implements TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $domainId;
    protected $alias;
    protected $name;
    protected $values;
    protected $active = 1;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($active)
    {
        $this->active = $active ? 1 : 0;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function setValues(array $values)
    {
        $this->values = $values;
    }

    public function getDomainId ()
    {
        return $this->domainId;
    }

    public function setDomainId ($domainId)
    {
        $this->domainId = $domainId;
    }
}