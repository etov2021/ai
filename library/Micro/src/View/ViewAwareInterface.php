<?php

namespace Micro\View;

interface ViewAwareInterface
{
    /**
     * @param View $view
     */
    public function setView(View $view);

    /**
     * @return View
     */
    public function getView();
}