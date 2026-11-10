<?php

namespace Micro\Grid\Column;

use Micro\Grid\Column;
use Micro\Router\Router;
use Micro\Container\SharedContainer;

class Href extends Column
{
    protected $params = [];
    protected $reset = \false;
    protected $qsa = \true;
    protected $qsar = \false;
    protected $hrefClass = '';

    /**
     * @var Router
     */
    protected $router;

    public function __construct($name, array $options = [])
    {
        parent::__construct($name, $options);

        $this->router = SharedContainer::getInstance()->get('router');
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function setReset($value)
    {
        $this->reset = (bool) $value;
    }

    public function setQsa($value)
    {
        $this->qsa = (bool) $value;
    }

    public function setQsar($value)
    {
        $this->qsar = (bool) $value;
    }

    public function setHrefClass($value)
    {
        $this->hrefClass = $value;
    }

    public function render()
    {
        $params = $this->params;
        $route  = isset($params['route']) ? $params['route'] : \null;

        unset($params['route']);

        foreach ($params as $k => $v) {
            if (\substr($v, 0, 1) === ':') {
                $field = \substr($v, 1);
                $params[$k] = $this->getCurrentValue($field);
            }
        }

        $value = parent::render();

        return '<a' . ($this->hrefClass ? ' class="' . $this->hrefClass . '"' : '') . ' href="' . $this->router->assemble($route, $params, $this->reset, $this->qsa, $this->qsar) . '">' . $value . '</a>';
    }
}