<?php

namespace UserManagement\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Micro\Container\SharedContainer;
use Micro\Auth\Storage\Session as AuthStorage;
use Micro\Auth\Auth as AuthCore;

class Auth
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $container = SharedContainer::getInstance();

        $namespaces = $container->get('config')->get('auth.namespace', []);

        if (!empty($namespaces)) {
            foreach ($namespaces as $regex => $namespace) {
                if (@\preg_match($regex, $request->getUri()->getPath())) {
                    AuthCore::getInstance()->setStorage(new AuthStorage($namespace));
                    break;
                }
            }
        }

        return $next($request, $response);
    }
}