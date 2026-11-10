<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\StatusProcurements as Model;
use Micro\Application\Controller\Admin;

class StatusProcurements extends Admin
{
    protected $model = Model::class;
}