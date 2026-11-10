<?php

namespace Micro\Grid\Renderer;

interface RendererInterface
{
    public function render();

    public function renderPagination();
}