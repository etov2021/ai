<?php

namespace Seo\Model\Entity;

use Micro\Model\Entity\EntityInterface;
use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;

class MetaRoute extends EntityAbstract implements EntityInterface, TranslationInterface
{
    use TranslationTrait;

    protected $id;
    protected $domainId;
    protected $route;
    protected $title;
    protected $description;
}