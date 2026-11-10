<?php

namespace Micro\Translator\Adapter;

abstract class AdapterAbstract implements AdapterInterface
{
    protected $translations = [];

    public function translate($key, $code)
    {
        if (!isset($this->translations[$code])) {
            $this->translations[$code] = $this->loadTranslations($code);
        }

        if (isset($this->translations[$code][$key])) {
            return $this->translations[$code][$key];
        }

        return $key;
    }

    public function hasTranslation($key, $code)
    {
        if (!isset($this->translations[$code])) {
            $this->translations[$code] = $this->loadTranslations($code);
        }

        return isset($this->translations[$code][$key]);
    }

    abstract protected function loadTranslations($code);
}