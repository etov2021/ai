<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;

class FinanceSources extends TranslationDatabaseAbstract
{
    protected $entity = Entity\FinanceSource::class;

    protected $table = Table\FinanceSources::class;
}