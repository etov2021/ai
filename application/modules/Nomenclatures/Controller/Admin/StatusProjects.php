<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\StatusProjects as Model;
use Micro\Application\Controller\Admin;

class StatusProjects extends Admin
{
    protected $model = Model::class;
}