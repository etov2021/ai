<?php

namespace Domains\Model\Entity;

use Micro\Model\Entity\EntityAbstract;

class Domain extends EntityAbstract
{
    protected $id;
    protected $name;
    protected $addresses;
    protected $defaultLanguage;
    protected $languages;
    protected $countryCode;
    protected $template;
    protected $protocol;
    protected $header;
    protected $footer;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getAddresses()
    {
        return $this->addresses;
    }

    public function setAddresses($addresses)
    {
        $this->addresses = $addresses;
    }

    public function getDefaultLanguage ()
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage ($defaultLanguage)
    {
        $this->defaultLanguage = $defaultLanguage;
    }

    public function getLanguages ()
    {
        return $this->languages;
    }

    public function setLanguages($languages)
    {
        $this->languages = is_array($languages) ? implode(',', $languages) : $languages;
    }

    public function getCountryCode()
    {
        return $this->countryCode;
    }

    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;
    }

    public function getTemplate ()
    {
        return $this->template;
    }

    public function setTemplate ($template)
    {
        $this->template = $template;
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function setHeader($header)
    {
        $this->header = $header;
    }

    public function getFooter()
    {
        return $this->footer;
    }

    public function setFooter($footer)
    {
        $this->footer = $footer;
    }
}