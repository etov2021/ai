<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusProcedures extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusProcedure::class;

    protected $table = Table\StatusProcedures::class;
}