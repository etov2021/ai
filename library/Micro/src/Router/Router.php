<?php

namespace Micro\Router;

use Micro\Exception\Exception as CoreException;
use Micro\Application\Utils;
use Micro\Container\ContainerAwareInterface;
use Micro\Container\ContainerAwareTrait;
use Psr\Http\Message\ServerRequestInterface as Request;
use Micro\Helper\StableSort;

class Router implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var array of Route
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $globalParams = [];

    /**
     * @var Route
     */
    protected $currentRoute;

    /**
     * @var array
     */
    protected $matches = [];

    /**
     * @var string
     */
    const URL_DELIMITER = '/';

    /**
     * @param string $uri
     * @param string $method
     * @return Route|null
     */
    public function match($uri, $method)
    {
        $uri = $this->normalizeUri($uri);

        $this->currentRoute = \null;

        foreach ($this->routes as $route) {

            $success = $route->match($uri, $method);

            $this->matches[$route->getName()] = [
                'success' => $success,
                'route'   => $route
            ];

            if ($success) {
                $this->currentRoute = $route;
                break;
            }
        }

        return $this->currentRoute;
    }

    /**
     * @return array $matches
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * @param string $pattern
     * @param \Closure|string $handler
     * @param string $name
     * @param array|null $methods
     * @throws \Exception
     * @return Route
     */
    public function map($pattern, $handler, $name = \null, array $methods = [])
    {
        if (\null === $name) {
            $name = preg_replace('~[^\w]~', '-', $pattern);
            $name = preg_replace('~[\-]+~', '-', $name);
            $name = trim('route-' . trim($name, '-'), '-');
            if (!empty($methods)) {
                $name .= '-' . implode('-', array_map('strtolower', $methods));
            }
        }

        $route = new Route(
            $name,
            $this->normalizeUri($pattern),
            $handler,
            $methods
        );

        return $this->addRoute($route);
    }

    /**
     * @param Route $route
     * @throws CoreException
     * @return \Micro\Router\Route
     */
    public function addRoute(Route $route)
    {
        $name = $route->getName();

        if (isset($this->routes[$name])) {
            throw new CoreException(sprintf('[' . __METHOD__ . '] Route "%s" already exists!', $name), 500);
        }

        $route->setContainer($this->container);

        $this->routes[$name] = $route;

        return $route;
    }

    /**
     * @param string $uri
     * @return string
     */
    protected function normalizeUri($uri)
    {
        if (empty($uri) || $uri[0] !== static::URL_DELIMITER) {
            $uri = static::URL_DELIMITER . $uri;
        }

        if ($uri !== static::URL_DELIMITER) {
            $uri = rtrim($uri, static::URL_DELIMITER);
        }

        return $uri;
    }

    /**
     * @param string $name
     * @param array $data
     * @param boolean $reset
     * @param boolean $queryStringAppend
     * @param boolean $queryStringAppendRouteOnly
     * @throws \Exception
     * @return string
     */
    public function assemble($name = \null, array $data = [], $reset = \false, $queryStringAppend = \true, $queryStringAppendRouteOnly = \false)
    {
        static $request, $basePath, $queryParams;

        if ($name === \null && $this->currentRoute instanceof Route) {
            $name = $this->currentRoute->getName();
        }


        if (empty($name) || !isset($this->routes[$name])) {
            throw new CoreException(\sprintf('[' . __METHOD__ . '] Route "%s" not found!', $name), 500);
        }

        $route = $this->routes[$name];

        $data += $this->globalParams;

        if ($request === \null) {

            $request = $this->container->get('request');

            if ($request instanceof Request) {
                if ($basePath === \null) {
                    $uri = $request->getUri();
                    $basePath = '/';
                    if (\method_exists($uri, 'getBasePath')) {
                        $basePath = $uri->getBasePath();
                    }
                }
                if ($queryParams === \null) {
                    $queryParams = $request->getQueryParams();
                }
            }
        }

        $assembled = $route->assemble($data, $reset);

        if ($queryStringAppend === \true) {

            $qp = $data;

            if ($queryStringAppendRouteOnly === \false) {
                $qp += (array) $queryParams;
            }

            $qp = \array_filter($qp, function ($el) {
                return $el !== null && (\is_scalar($el) || \is_array($el));
            });

            if (!empty($qp)) {
                $assembled .= '?' . \http_build_query($qp);
            }
        }

        if ($assembled === self::URL_DELIMITER) {
            return (string) $basePath;
        }

        return (string) $basePath . \rtrim($assembled, static::URL_DELIMITER);
    }

    /**
     * @param string $key
     * @param string $value
     * @return Router
     */
    public function setGlobalParam($key, $value)
    {
        $this->globalParams[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return mixed
     */
    public function getGlobalParam($key, $value = \null)
    {
        if (isset($this->globalParams[$key])) {
            return $this->globalParams[$key];
        }

        return $value;
    }

    /**
     * @return array of Route 's
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param array $routes
     * @return Router
     */
    public function setRoutes(array $routes)
    {
        $this->routes = $routes;

        return $this;
    }

    /**
     * @param string $name
     * @return Route|\null
     */
    public function getRoute($name)
    {
        if (isset($this->routes[$name])) {
            return $this->routes[$name];
        }

        return \null;
    }

    /**
     * @return Route
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     * @return Router
     */
    public function loadDefaultRoute($name, $config)
    {
        $defaults = [
            'module' => 'app',
            'controller' => 'index',
            'action' => 'index',
            'id' => \null
        ];

        $onAssemble = function ($data) use ($config) {

            $defaults = $this->getDefaults();

            if ($data['id'] === $defaults['id']) {
                if ($data['action'] === $defaults['action']) {
                    if ($data['controller'] === $defaults['controller']) {
                        if ($data['module'] === $defaults['module'] && (!isset($config['literal']) || $config['literal'])) {
                            unset($data['module']);
                        }
                        unset($data['controller']);
                    }
                    unset($data['action']);
                }
                unset($data['id']);
            }

            return $data;
        };

        if (!isset($this->routes[$name])) {

            $preffix = ($config['literal'] ? $config['literal'] . '[/{module}]' : '{module}');

            $methods = [];

            if (isset($config['methods'])) {
                $methods = (array) $config['methods'];
            }

            $handler = function () use ($config) {

                $params = $this->getParams();

                $module = \ucfirst(Utils::camelize($params['module']));
                $controller = \ucfirst(Utils::camelize($params['controller']));
                $action = \lcfirst(Utils::camelize($params['action']));

                return $module . '\\' . (isset($config['namespace']) ? $config['namespace'] : '') . $controller . '@' . $action;

            };

            $route = $this->map('/' . $preffix . '[/{controller}][/{action}][/{id}]', $handler, $name, $methods);

            $routeDefaultParams = $defaults;

            if (isset($config['defaults'])) {
                $routeDefaultParams = $config['defaults'] + $defaults;
            }

            $route->setDefaults($routeDefaultParams);

            if (isset($config['conditions'])) {
                $route->setConditions($config['conditions']);
            }

            if (isset($config['on_assemble'])) {
                $route->setOnAssemble($this->makeEventClosure($config['on_assemble']));
            } else {
                $route->setOnAssemble($onAssemble);
            }

            if (isset($config['on_match'])) {
                $route->setOnMatch($this->makeEventClosure($config['on_match']));
            }

            if (isset($config['middlewares'])) {
                foreach ((array) $config['middlewares'] as $middleware) {
                    if (is_array($middleware)) {
                        $route->add($middleware[0], $middleware[1]);
                    } else {
                        $route->add($middleware);
                    }
                }
            }

            if (isset($config['description'])) {
                $route->setDescription($config['description']);
            }

            if (isset($config['options'])) {
                $route->setOptions($config['options']);
            }
        }

        return $this;
    }

    /**
     * @param array $routes
     * @return Router
     * @throws CoreException
     */
    public function mapFromConfig(array $routes)
    {
        StableSort::uasort($routes, function ($a, $b) {
            $aOrder = isset($a['order']) ? $a['order'] : 0;
            $bOrder = isset($b['order']) ? $b['order'] : 0;
            return $aOrder < $bOrder ? -1 : ($aOrder > $bOrder ? 1 : 0);
        });

        foreach ($routes as $name => $config) {

            if (isset($config['type'])) {
                $typedRoute = $config['type'];
                if (\class_exists($typedRoute)) {
                    $this->addRoute((new $typedRoute($this))->createRoute($name, $config));
                    continue;
                }
            }

            if (isset($config['default_route']) && $config['default_route']) {
                $this->loadDefaultRoute($name, $config);
                continue;
            }

            if (!isset($config['pattern']) || !isset($config['handler'])) {
                continue;
            }

            $methods = [];
            if (isset($config['methods'])) {
                $methods = (array) $config['methods'];
            }

            $route = $this->map(
                $config['pattern'],
                $config['handler'],
                $name,
                $methods
            );

            if (isset($config['conditions'])) {
                $route->setConditions($config['conditions']);
            }

            if (isset($config['defaults'])) {
                $route->setDefaults($config['defaults']);
            }

            if (isset($config['on_assemble'])) {
                $route->setOnAssemble($this->makeEventClosure($config['on_assemble']));
            }

            if (isset($config['on_match'])) {
                $route->setOnMatch($this->makeEventClosure($config['on_match']));
            }

            if (isset($config['compiled'])) {
                $route->setCompiled($config['compiled']);
            }

            if (isset($config['middlewares'])) {
                foreach ((array) $config['middlewares'] as $middleware) {
                    if (is_array($middleware)) {
                        $route->add($middleware[0], $middleware[1]);
                    } else {
                        $route->add($middleware);
                    }
                }
            }

            if (isset($config['description'])) {
                $route->setDescription($config['description']);
            }

            if (isset($config['options'])) {
                $route->setOptions($config['options']);
            }
        }

        return $this;
    }

    protected function makeEventClosure($on)
    {
        if (\is_string($on) && \class_exists($on, \true)) {
            $onInstance = new $on;
            if (\is_callable($onInstance)) {
                $on = function () use ($onInstance) {
                    return call_user_func($onInstance, $this);
                };
            }
        }

        if (!$on instanceof \Closure) {
            return \null;
        }

        return $on;
    }
}