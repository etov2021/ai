<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\StatusPrograms as Model;
use Micro\Application\Controller\Admin;

class StatusPrograms extends Admin
{
    protected $model = Model::class;
}