<?php

namespace App\Controller;

use Micro\Http\Response\JsonResponse;
use Micro\Application\Controller;
use Micro\Application\Application;

class Error extends Controller
{
    const ERROR = 'Page not found.';

    public function indexAction()
    {
        $exception = $this->request->getParam('exception');

        if (!$exception instanceof \Exception && !$exception instanceof \Throwable) {
            return ['message' => static::ERROR];
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

        if (!\is_integer($code) || $code < 100 || $code > 599) {
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
                ];
            }

            return new JsonResponse(['error' => $data], $httpCode);
        }

        try {
            $this->response->withStatus($httpCode);
        } catch (\Exception $e) {
            $this->response->withStatus(500);
        }

        return ['exception' => $exception, 'message' => $message];
    }
}