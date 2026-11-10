<?php

namespace Domains\Model\Entity;

interface DomainInterface
{
    public function getDomainId();
    public function setDomainId($domainId);
    public function getDomainSettings();
    public function getDomain();

    public function getDomains();
    public function setDomains($domains);
}