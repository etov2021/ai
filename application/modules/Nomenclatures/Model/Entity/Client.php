<?php

namespace Nomenclatures\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class Client extends EntityAbstract implements TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $name;
    protected $abbr;
    protected $active = 1;
}