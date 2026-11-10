<?php

namespace Domains\Model\Entity;

use Domains\Model\Domains as ModelDomains;

trait DomainTrait
{
    protected $domainId;
    protected $domain;
    protected $domains;

    public function getDomainId()
    {
        return $this->domainId;
    }

    public function setDomainId($domainId)
    {
        $this->domainId = $domainId;

        return $this;
    }

    public function getDomainSettings()
    {
        return [];
    }

    public function getDomain()
    {
        $id = $this->getDomainId();

        if ($this->domain === \null && $id) {
            $this->domain = (new ModelDomains())->addWhere('id', $id)->getItem();
        }

        return $this->domain;
    }

    public function getDomains()
    {
        return $this->domains;
    }

    public function setDomains($domains)
    {
        $this->domains = $domains;

        return $this;
    }
}