<?php

namespace App\Controller\Admin;

use Micro\Http\Response\JsonResponse;
use Micro\Auth\Auth;
use Micro\Http\Response\RedirectResponse;
use Micro\Application\Controller;
use Micro\Application\Application;

class Error extends Controller
{
    public $scope = 'admin';

    const ERROR = 'Страницата не е намерена';

    public function indexAction()
    {
        $exception = $this->request->getParam('exception');

        if (!$exception instanceof \Throwable) {
            return ['exception' => $exception, 'message' => static::ERROR];
        }

        $code = $exception->getCode() ?: 404;

        switch ($exception->getCode()) {
            case Application::ERROR_ROUTE_NOT_FOUND :
            case Application::ERROR_HANDLER_NOT_RESOLVED :
            case Application::ERROR_HANDLER_NOT_FOUND :
            case Application::ERROR_ACTION_NOT_FOUND :
                $code = 404;
                break;
        }

        $httpCode = $code;

        if (!is_integer($code) || $code < 100 || $code > 599) {
            $httpCode = 500;
        }

        if (in_array($code, [700])) {
            $httpCode = 200;
        }

        $message = (is_debug() || $code === 403 || $code === 700 ? $exception->getMessage() : static::ERROR);

        if ($this->request->isAjax()) {

            $data = [
                'message' => $message,
            ];

            if (is_debug()) {

                $data = $data + [
                    'code'  => $exception->getCode(),
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ];
            }

            return new JsonResponse(['error' => $data], $httpCode);
        }

        if ($exception->getCode() === 403) {
            if (Auth::identity() === null) {
                $router = $this->container->get('router');
                $route  = $router->getRoute('admin-login');
                if ($route && $router->getCurrentRoute() && $router->getCurrentRoute()->getName() !== $route->getName()) {
                    $params = [
                        'backTo' => route(),
                    ];
                    return new RedirectResponse(route_reset('admin-login', $params));
                }
            }
        }

        try {
            $this->response->withStatus($httpCode);
        } catch (\Exception $e) {
            $this->response->withStatus(500);
        }

        return ['exception' => $exception, 'message' => $message];
    }
}