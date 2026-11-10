<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\Summary as Model;
use Micro\Application\Controller\Admin;

class Summary extends Admin
{
    protected $model = Model::class;
}