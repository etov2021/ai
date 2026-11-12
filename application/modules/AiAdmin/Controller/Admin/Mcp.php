<?php

namespace AiAdmin\Controller\Admin;

use AiAdmin\Service\BaseController;

class Mcp extends BaseController
{
    public function indexAction()
    {
        $refresh = (string) ($this->request->getQueryParams()['refresh'] ?? '') === '1';

        return [
            'servers' => $this->ai()->mcpProvider()->describe($refresh),
            'refreshUrl' => $this->url('mcp', 'index', null, ['refresh' => 1]),
            'homeUrl' => $this->url('index'),
        ];
    }
}
