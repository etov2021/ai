<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusProcurements extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusProcurement::class;

    protected $table = Table\StatusProcurements::class;
}