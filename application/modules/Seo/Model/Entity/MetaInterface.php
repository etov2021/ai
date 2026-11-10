<?php

namespace Seo\Model\Entity;

use Micro\Model\Entity\TargetInterface;

interface MetaInterface extends TargetInterface
{
    public function getMetaTitle();
    public function getMetaDescription();
}