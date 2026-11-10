<?php

namespace Micro\Navigation\Page;

use Micro\Router\Router;
use Micro\Container\SharedContainer;
use Micro\Http\Request;

class Page extends AbstractPage
{
    protected $alias;
    protected $href;
    protected $uri;
    protected $routeParams = [];
    protected $route;
    protected $reset = \true;
    protected $targetBlank = \false;
    protected $qsa = \false;
    protected $qsar = \false;
    protected $active;
    protected $router;
    protected $request;

    /**
     * @return Router
     */
    public function getRouter()
    {
        if ($this->router === \null) {
            $this->router = SharedContainer::getInstance()->get('router');
        }

        return $this->router;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        if ($this->request === \null) {
            $this->request = SharedContainer::getInstance()->get('request');
        }

        return $this->request;
    }

    public function isActive($recursive = \false)
    {
        if ($this->uri !== \null) {
            $this->detectUri();
        } else {
            $this->detectRoute();
        }

        if ($this->active === \false && $recursive) {
            foreach ($this->pages as $child) {
                if ($child instanceof Page && $child->isActive(\true)) {
                    return \true;
                }
            }
            return \false;
        }

        return $this->active;
    }

    public function detectUri()
    {
        if (\null === $this->active) {
            if (preg_match('~^' . preg_quote($this->getRequest()->getUri()->getPath(), '~'). '$~ius', $this->uri)) {
                $this->active = \true;
            } else {
                $this->active = \false;
            }
        }
    }

    public function detectRoute()
    {
        if (\null === $this->active) {

            $router = $this->getRouter();

            $route = $router->getCurrentRoute();

            if ($route === \null || $route->getName() !== $this->route) {
                $this->active = \false;
                return;
            }

            $pageRoute = $router->getRoute($this->route);

            $myParams  = $this->routeParams + ($pageRoute ? $pageRoute->getDefaults() : []);

            foreach ($myParams as $key => $value) {
                if (\null === $value) {
                    unset($myParams[$key]);
                }
            }

            if (\count(\array_intersect_assoc($this->getRequest()->getParams(), $myParams)) == \count($myParams)) {
                $this->active = \true;
            } else {
                $this->active = \false;
            }
        }
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getHref()
    {
        if (\null === $this->href) {
            if ($this->uri !== \null) {
                $this->href = (string) $this->uri;
            } else if ($this->route !== \null) {
                try {
                    $this->href = (string) $this->getRouter()->assemble($this->route, $this->routeParams, $this->reset, $this->qsa, $this->qsar);
                } catch (\Throwable $e) {
                    $this->label = $e->getMessage();
                    $this->href  = '#';
                }
            } else {
                $this->href  = '#';
            }
        }

        return $this->href;
    }

    public function setUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    public function setRouteParams(array $routeParams)
    {
        $this->routeParams = $routeParams;

        return $this;
    }

    public function getRouteParams()
    {
        return $this->routeParams;
    }

    public function getRouteParam($param = null, $default = null)
    {
        if ($param && isset($this->routeParams[$param])) {
            return $this->routeParams[$param];
        }

        return $default;
    }

    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function setReset($reset)
    {
        $this->reset = (bool) $reset;

        return $this;
    }

    public function setTargetBlank($flag)
    {
        $this->targetBlank = (bool) $flag;

        return $this;
    }

    public function getTargetblank()
    {
        return $this->targetBlank;
    }

    public function isTargetblank()
    {
        return $this->targetBlank;
    }

    public function setQsa($qsa)
    {
        $this->qsa = (bool) $qsa;

        return $this;
    }

    public function setQsar($qsar)
    {
        $this->qsar = (bool) $qsar;

        return $this;
    }

    public function isAllowed($role = \null, $privilege = \true)
    {
        if ($this->uri !== \null
            || $this->resource === \null
            || (\function_exists('is_allowed') && \is_allowed($this->resource, $role, $privilege))
        ) {
            return \true;
        }

        return \false;
    }
}