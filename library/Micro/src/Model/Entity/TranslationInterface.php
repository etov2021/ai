<?php

namespace Micro\Model\Entity;

use Micro\Translator\Language\LanguageInterface;

interface TranslationInterface
{
    public function getLanguageId();
    public function setLanguageId($languageId);
    public function getTranslations();
    public function setTranslations($translations);
    public function hasTranslation(LanguageInterface $language);
    public function addTranslation(LanguageInterface $language);
    public function removeTranslation(LanguageInterface $language);
}