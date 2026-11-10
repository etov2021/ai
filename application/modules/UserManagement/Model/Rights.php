<?php

namespace UserManagement\Model;

use Micro\Model\DatabaseAbstract;

class Rights extends DatabaseAbstract
{
    protected $entity = Entity\Right::class;

    protected $table = Table\Rights::class;
}