<?php

namespace Micro\Model;

use Micro\Translator\Language\LanguageInterface;

interface TranslationInterface
{
    public function getLanguage();
    public function setLanguage(LanguageInterface $language);
}