<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class StatusProjects extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusProject::class;

    protected $table = Table\StatusProjects::class;
}