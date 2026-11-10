<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusContracts extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusContract::class;

    protected $table = Table\StatusContracts::class;
}