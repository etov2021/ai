<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class TypeChange extends TranslationDatabaseAbstract
{
    protected $entity = Entity\TypeChange::class;

    protected $table = Table\TypeChange::class;
}