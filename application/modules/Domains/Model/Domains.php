<?php

namespace Domains\Model;

use Micro\Model\DatabaseAbstract;

class Domains extends DatabaseAbstract
{
    protected $entity = Entity\Domain::class;

    protected $table = Table\Domains::class;
}