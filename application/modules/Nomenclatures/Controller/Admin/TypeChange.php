<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\TypeChange as Model;
use Micro\Application\Controller\Admin;

class TypeChange extends Admin
{
    protected $model = Model::class;
}