<?php

namespace Seo\Model\Entity;

use Micro\Model\Entity\EntityInterface;
use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class Meta extends EntityAbstract implements EntityInterface, TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $target;
    protected $targetId;
    protected $title;
    protected $description;
}