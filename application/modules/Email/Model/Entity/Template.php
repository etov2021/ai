<?php

namespace Email\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Entity\DomainTrait;

class Template extends EntityAbstract implements TranslationInterface, DomainInterface
{
    use TranslationTrait, DomainTrait;

    protected $id;
    protected $alias;
    protected $subject;
    protected $description;
    protected $descriptionIsEditor = 1;
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

    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($active)
    {
        $this->active = $active ? 1 : 0;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return the $descriptionIsEditor
     */
    public function getDescriptionIsEditor()
    {
        return $this->descriptionIsEditor;
    }

    /**
     * @param number $descriptionIsEditor
     */
    public function setDescriptionIsEditor($descriptionIsEditor)
    {
        $this->descriptionIsEditor = $descriptionIsEditor;
    }
}