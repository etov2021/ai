<?php

namespace Translations\Controller\Admin;

use Translations\Model\Languages as ModelLanguages;
use Micro\Application\Controller\Admin;

class Languages extends Admin
{
    protected $model = ModelLanguages::class;
}