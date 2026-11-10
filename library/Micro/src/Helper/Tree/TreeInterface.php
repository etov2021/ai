<?php

namespace Micro\Helper\Tree;

interface TreeInterface
{
    public function loadTree($active = 1);
    public function getTree($active = 1);
    public function flat(array $tree, $glue = '-',  ?array $exclude = \null);
}