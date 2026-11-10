<?php

namespace Translations\View\Helper;

use Micro\View\View;
use Micro\View\Helper\HelperAbstract;

class Languages extends HelperAbstract
{
    public function __invoke($viewScript = null)
    {
        if ($viewScript === null) {
            $viewScript = 'modules::Translations/View/Helper/Resources/views/languages';
        }

        return $this->view->partial($viewScript);
    }
}