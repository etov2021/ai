<?php

namespace Micro\Application\Controller;

use Micro\Acl\AuthorizeInterface;

class Admin extends Crud implements AuthorizeInterface
{
    public $scope = 'admin';
}