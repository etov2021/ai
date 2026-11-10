<?php

namespace Micro\Translator\Adapter;

interface AdapterInterface
{
    public function translate($key, $code);
}