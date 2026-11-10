<?php

namespace Micro\View\Widget;

use Micro\View\View;

abstract class WidgetAbstract implements WidgetInterface
{
    /**
     * @var View
     */
    protected $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }
}