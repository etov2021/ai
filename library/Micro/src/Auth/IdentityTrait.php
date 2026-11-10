<?php

namespace Micro\Auth;

trait IdentityTrait
{
    protected $id;
    protected $username;
    protected $password;
    protected $email;
    protected $groups;
    protected $currentGroup;
    protected $active = 1;

    public function getId()
    {
        return $this->id;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getCurrentGroup()
    {
        return $this->currentGroup;
    }

    public function isActive()
    {
        return $this->active;
    }
}