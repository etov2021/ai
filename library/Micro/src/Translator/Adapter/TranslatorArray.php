<?php

namespace Micro\Translator\Adapter;

use Micro\Container\SharedContainer;

class TranslatorArray extends AdapterAbstract
{
    protected $path;

    public function __construct()
    {
        $this->path = SharedContainer::getInstance()->get('config')->get('translator.options.path');
    }

    protected function loadTranslations($code)
    {
        $translations = [];

        if ($this->path !== \null) {
            $files = \glob(\rtrim($this->path, '/') . '/' . $code . '/*');
            if (!empty($files)) {
                \asort($files);
                foreach ($files as $file) {
                    $fileTranslations = include $file;
                    $fileTranslations = \is_array($fileTranslations) ? $fileTranslations : [];
                    $translations = \array_merge($translations, $fileTranslations);
                }
            }
        }

        return $translations;
    }
}