<?php

namespace Navigation\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;
use Files\Entity\FileInterface;
use Files\Entity\FileTrait;
use Micro\Model\Entity\TargetTrait;

class Item extends EntityAbstract implements TranslationInterface, FileInterface
{
    use TranslationTrait, FileTrait, TargetTrait;

    protected $id;
    protected $parentId;
    protected $handlerId;
    protected $handlerPosition = 0;
    protected $menuId;
    protected $name;
    protected $alias;
    protected $description;
    protected $url;
    protected $resource;
    protected $route;
    protected $reset = 1;
    protected $targetBlank = 0;
    protected $qsaData;
    protected $routeData;
    protected $active = 1;
    protected $visible = 1;
    protected $order;
    protected $class;
    protected $image;

    public function getFileSettings()
    {
        $settings = [
            'image' => [
                'required' => 0,
                'validators' => [
                    'extension' => [
                        'validator' => 'File\Extension',
                        'options'   => [
                            'accept' => ['image/gif', 'image/png', 'image/bmp', 'image/jpeg'],
                        ],
                    ],
                ],
            ],
        ];

        return $settings;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function setParentId($parentId)
    {
        $this->parentId = $parentId;
    }

    public function getMenuId()
    {
        return $this->menuId;
    }

    public function setMenuId($id)
    {
        $this->menuId = $id;
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

    public function getRoute()
    {
        return $this->route;
    }

    public function setRoute($route)
    {
        $this->route = $route;
    }

    public function getRouteData()
    {
        return $this->routeData;
    }

    public function setRouteData($routeData)
    {
        $this->routeData = $routeData;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($active)
    {
        $this->active = $active;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setVisible($visible)
    {
        $this->visible = $visible;
    }

    public function getReset()
    {
        return $this->reset;
    }

    public function getQsaData()
    {
        return $this->qsaData;
    }

    public function setQsaData($qsaData)
    {
        $this->qsaData = $qsaData;
    }

    public function setReset($reset)
    {
        $this->reset = $reset;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($order)
    {
        $this->class = $order;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setResource($resource)
    {
        $this->resource = $resource;
    }

    public function getTargetBlank()
    {
        return $this->targetBlank;
    }

    public function setTargetBlank($targetBlank)
    {
        $this->targetBlank = $targetBlank;
    }

    public function getHandlerId()
    {
        return $this->handlerId;
    }

    public function setHandlerId($handlerId)
    {
        $handlerId = is_array($handlerId) ? implode(',', $handlerId) : $handlerId;
        $handlerId = empty($handlerId) ? null : $handlerId;

        $this->handlerId = $handlerId;
    }

    public function getHandlerPosition()
    {
        return $this->handlerPosition;
    }

    public function setHandlerPosition($handlerPosition)
    {
        $this->handlerPosition = $handlerPosition;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }
}