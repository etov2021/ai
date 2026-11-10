<?php

namespace Micro\Auth;

interface Identity
{
    public function getId();
    public function getUsername();
    public function getPassword();
    public function getEmail();
    public function getGroups();
    public function getCurrentGroup();
    public function isActive();
}