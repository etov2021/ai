<?php

namespace Nomenclatures\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class FinanceSource extends EntityAbstract implements TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $name;
    protected $active = 1;
}