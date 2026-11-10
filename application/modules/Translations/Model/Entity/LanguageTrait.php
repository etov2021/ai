<?php

namespace Translations\Model\Entity;

trait LanguageTrait
{
    protected $languageId;

    public function getLanguageId()
    {
        return $this->languageId;
    }

    public function setLanguageId($languageId)
    {
        $this->languageId = $languageId;

        return $this;
    }
}