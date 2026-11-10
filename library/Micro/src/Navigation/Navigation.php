<?php

namespace Micro\Navigation;

use Traversable;

class Navigation extends AbstractContainer
{
    /**
     * Creates a new navigation container
     *
     * @param  array|Traversable $pages    [optional] pages to add
     * @throws \InvalidArgumentException  if $pages is invalid
     */
    public function __construct($pages = \null)
    {
        if ($pages && (!is_array($pages) && !$pages instanceof Traversable)) {
            throw new \InvalidArgumentException(
                'Invalid argument: $pages must be an array, an '
                . 'instance of Traversable, or null'
            );
        }
        if ($pages) {
            $this->addPages($pages);
        }
    }
}