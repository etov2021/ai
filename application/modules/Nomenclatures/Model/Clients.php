<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class Clients extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Client::class;

    protected $table = Table\Clients::class;
}