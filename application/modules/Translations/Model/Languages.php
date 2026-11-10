<?php

namespace Translations\Model;

use Micro\Model\DatabaseAbstract;

class Languages extends DatabaseAbstract
{
    protected $table = Table\Languages::class;

    protected $entity = Entity\Language::class;
}


