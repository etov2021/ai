<?php

namespace Translations\Model\Entity;

use Micro\Translator\Language\LanguageInterface;
use Micro\Model\Entity\EntityAbstract;

class Language extends EntityAbstract implements LanguageInterface
{
    protected $id;
    protected $code;
    protected $erpCode;
    protected $name;
    protected $locale;
    protected $active = 1;

    public function __construct($id = \null, $code = \null)
    {
        $this->id = $id;
        $this->code = $code;
        $this->locale = $code;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getActive()
    {
        return $this->active;
    }
}