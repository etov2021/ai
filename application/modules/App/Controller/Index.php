<?php

namespace App\Controller;

use Micro\Application\Controller;
use Micro\Http\Response\RedirectResponse;

class Index extends Controller
{
    public function indexAction()
    {
        return new RedirectResponse(route_reset('admin-login'));
    }
}
