<?php

namespace Micro\Application;

use RuntimeException;
use UnexpectedValueException;
use Micro\Container\ContainerInterface;
use Micro\Exception\Exception as CoreException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

trait MiddlewareAwareTrait
{
    /**
     * @var array
     */
    protected $middlewarePending = [];

    /**
     * @var array
     */
    protected $middlewareStack;

    /**
     * @var bool
     */
    protected $middlewareLock = \false;

    /**
     * @param mixed $middleware
     * @param int $priority
     * @return self
     */
    public function add($middleware, $priority = 1)
    {
        if (\is_array($middleware)) {
            foreach ($middleware as $k => $v) {
                if (\is_int($k)) {
                    $this->add($v, $priority);
                } else {
                    $this->add($k, $v);
                }
            }
            return $this;
        }

        if (!isset($this->middlewarePending[$priority])) {
            $this->middlewarePending[$priority] = [];
        }

        $this->middlewarePending[$priority][] = $middleware;

        return $this;
    }

    /**
     * @return boolean
     */
    public function hasMiddleware()
    {
        return !empty($this->middlewarePending);
    }

    /**
     * Add middleware
     *
     * This method prepends new middleware to the application middleware stack.
     *
     * @param callable $callable Any callable that accepts three arguments:
     *                           1. A Request object
     *                           2. A Response object
     *                           3. A "next" middleware callable
     * @return static
     *
     * @throws RuntimeException         If middleware is added while the stack is dequeuing
     * @throws UnexpectedValueException If the middleware doesn't return a Response
     */
    protected function addMiddleware(callable $callable)
    {
        if ($this->middlewareLock) {
            throw new RuntimeException('Middleware can’t be added once the stack is dequeuing');
        }

        if ($this->middlewareStack === \null) {
            $this->seedMiddlewareStack();
        }

        $next = \array_pop($this->middlewareStack);

        $this->middlewareStack[] = function (Request $req, Response $res) use ($callable, $next) {

            $result = \call_user_func($callable, $req, $res, $next);

            if ($result instanceof Response === \false) {
                throw new UnexpectedValueException(
                    sprintf('Middleware "%s" must return instance of ' . Response::class, \get_class($callable))
                );
            }

            return $result;
        };

        return $this;
    }

    /**
     * Seed middleware stack with first callable
     *
     * @param callable $middleware
     * @return $this
     * @internal param callable $kernel The last item to run as middleware
     */
    protected function seedMiddlewareStack(?callable $middleware = null)
    {
        if ($this->middlewareStack !== \null) {
            throw new RuntimeException('MiddlewareStack can only be seeded once.');
        }

        if ($middleware === \null) {
            $middleware = $this;
        }

        $this->middlewareStack = [$middleware];

        return $this;
    }

    /**
     * Call middleware stack
     *
     * @param  Request            $req A request object
     * @param  Response           $res A response object
     * @param  ContainerInterface $container A container object
     *
     * @return Response
     */
    public function callMiddlewareStack(Request $req, Response $res, ContainerInterface $container)
    {
        if ($this->middlewareStack === \null) {
            $this->seedMiddlewareStack();
        }

        ksort($this->middlewarePending);

        foreach ($this->middlewarePending as $priority => $middlewares) {

            \krsort($middlewares);

            foreach ($middlewares as $k => $middleware) {

                unset($this->middlewarePending[$priority][$k]);

                if (\is_string($middleware) && $container->has($middleware)) {
                    $middleware = $container->get($middleware);
                } elseif (\is_string($middleware) && \class_exists($middleware)) {
                    $middleware = new $middleware;
                }

                if (!\is_callable($middleware)) {
                    throw new CoreException(\sprintf('"%s" is not callable at "%s"', (
                        \is_object($middleware)
                        ? \get_class($middleware)
                        : (
                            \is_string($middleware)
                            ? $middleware
                            : \gettype($middleware)
                        )
                    ), __METHOD__));
                }

                $this->addMiddleware($middleware);
            }

            unset($this->middlewarePending[$priority]);
        }

        $start = \array_pop($this->middlewareStack);

        $this->middlewareLock = \true;

        $res = $start($req, $res);

        $this->middlewareLock = \false;

        return $res;
    }
}
