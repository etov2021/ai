<?php

namespace Files\Entity;

use Micro\Model\Entity\TargetInterface;

interface FileInterface extends TargetInterface
{
    public function getFileSettings();

    public function getFiles($type);
}