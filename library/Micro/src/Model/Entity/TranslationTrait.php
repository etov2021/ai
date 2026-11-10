<?php

namespace Micro\Model\Entity;

use Micro\Translator\Language\LanguageInterface;

trait TranslationTrait
{
    protected $languageId;

    protected $translations = 0;

    public function getLanguageId()
    {
        return $this->languageId;
    }

    public function setLanguageId($languageId)
    {
        $this->languageId = $languageId;

        return $this;
    }

    public function getTranslations()
    {
        return $this->translations;
    }

    public function setTranslations($translations)
    {
        $this->translations = $translations;

        return $this;
    }

    public function hasTranslation(LanguageInterface $language)
    {
        return (bool) ($this->translations & (\pow(2, $language->getId() - 1)));
    }

    public function addTranslation(LanguageInterface $language)
    {
        $this->translations |= \pow(2, $language->getId() - 1);

        return $this;
    }

    public function removeTranslation(LanguageInterface $language)
    {
        $this->translations &= ~(\pow(2, $language->getId() - 1));

        return $this;
    }
}