<?php

namespace Categories\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;
use Seo\Model\Entity\MetaInterface;
use Seo\Model\Entity\MetaTrait;
use Micro\Model\Entity\TargetTrait;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Entity\DomainTrait;

class Category extends EntityAbstract implements TranslationInterface, MetaInterface, DomainInterface
{
    use TranslationTrait, MetaTrait, TargetTrait, DomainTrait;

    protected $id;
    protected $categoryTarget;
    protected $alias;
    protected $permalink;
    protected $parentId;
    protected $name;
    protected $active = 1;
    protected $order;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return the $parentId
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * @param field_type $parentId
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;
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

    /**
     * @return the $alias
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param field_type $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @return the $categoryTarget
     */
    public function getCategoryTarget()
    {
        return $this->categoryTarget;
    }

    /**
     * @param field_type $categoryTarget
     */
    public function setCategoryTarget($categoryTarget)
    {
        $this->categoryTarget = $categoryTarget;
    }

    /**
     * @return the $permalink
     */
    public function getPermalink()
    {
        return $this->permalink;
    }

    /**
     * @param field_type $permalink
     */
    public function setPermalink($permalink)
    {
        $this->permalink = $permalink;
    }
}