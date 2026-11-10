<?php

namespace Micro\View\Helper;

use Micro\View\View;

abstract class HelperAbstract
{
    /**
     * @var View
     */
    protected $view;

    /**
     * @param View $view
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }
}