<?php

namespace Micro\Translator\Adapter;

use Micro\Container\SharedContainer;

class Database extends AdapterAbstract
{
    protected $model;

    public function getModel()
    {
        if ($this->model === \null) {
            $model = SharedContainer::getInstance()->get('config')->get('translator.options.model');
            if ($model && \class_exists($model)) {
                $this->model = new $model();
            } else {
                $this->model = \false;
            }
        }

        return $this->model;
    }

    protected function loadTranslations($code)
    {
        $translations = [];

        $model = $this->getModel();

        if (\is_object($model) && \method_exists($model, 'getTranslations')) {
            try {
                $translations = $model->getTranslations($code);
                $translations = \is_array($translations) ? $translations : [];
            } catch (\Throwable $e) {

            }
        }

        return $translations;
    }
}