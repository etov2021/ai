<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\StatusContracts as Model;
use Micro\Application\Controller\Admin;

class StatusContracts extends Admin
{
    protected $model = Model::class;
}