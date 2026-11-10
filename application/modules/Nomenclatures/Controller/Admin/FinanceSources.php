<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\FinanceSources as Model;
use Micro\Application\Controller\Admin;

class FinanceSources extends Admin
{
    protected $model = Model::class;
}