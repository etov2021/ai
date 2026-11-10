<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusPrograms extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusProgram::class;

    protected $table = Table\StatusPrograms::class;
}