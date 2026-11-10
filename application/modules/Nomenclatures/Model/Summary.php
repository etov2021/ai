<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class Summary extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Summary::class;

    protected $table = Table\Summary::class;
}