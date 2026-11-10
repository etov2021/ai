<?php

namespace Micro\Paginator\Adapter;

interface AdapterInterface extends \Countable
{
    public function getItems($offset = \null, $itemCountPerPage = \null);
}