<?php

namespace UserManagement\Model\Entity;

trait RightsTrait
{
    protected $rights;
    protected $customRights;

    public function getRights()
    {
        return $this->rights;
    }

    public function setRights($rights)
    {
        $this->rights = $rights;
    }

    public function getCustomRights()
    {
        return $this->customRights;
    }

    public function setCustomRights($customRights)
    {
        $this->customRights = $customRights;
    }
}