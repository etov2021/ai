<?php

namespace UserManagement\Model\Entity;

use Micro\Model\Entity\EntityAbstract;

class Group extends EntityAbstract
{
    use RightsTrait;

    protected $id;
    protected $name;
    protected $alias;
    protected $active = 0;
    protected $parentId;
    protected $fileManagerPersonalFolder = 0;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($value)
    {
        $this->active = $value ? 1 : 0;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function setParentId($parentId)
    {
        $this->parentId = ($parentId ?: null);
    }


    public function getFileManagerPersonalFolder()
    {
        return $this->fileManagerPersonalFolder;
    }

    public function setFileManagerPersonalFolder($fileManagerPersonalFolder)
    {
        $this->fileManagerPersonalFolder = $fileManagerPersonalFolder;
    }
}