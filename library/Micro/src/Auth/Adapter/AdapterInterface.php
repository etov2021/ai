<?php

namespace Micro\Auth\Adapter;

interface AdapterInterface
{
    /**
     * Performs an authentication attempt
     *
     * @return int
     */
    public function authenticate();
}
