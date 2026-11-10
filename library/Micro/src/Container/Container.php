<?php

namespace Micro\Container;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    protected $services = [];

    /**
     * @var array
     */
    protected $resolved = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var object
     */
    protected $binder;

    /**
     * @var array of bindings
     */
    protected $bindings = [];

    /**
     * @var array of resolved bindings
     */
    protected $ranBinders = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->configure($config);
        }
    }

    /**
     * @return Container
     */
    public function setAsGlobal()
    {
        SharedContainer::setInstance($this);

        return $this;
    }

    /**
     * @param array $config
     */
    public function configure(array $config)
    {
        if (isset($config['services'])) {
            foreach ($config['services'] as $id => $service) {
                $this->set($id, $service);
            }
        }

        if (isset($config['aliases'])) {
            foreach ($config['aliases'] as $alias => $service) {
                $this->alias($alias, $service);
            }
        }

        if (isset($config['global']) && $config['global']) {
            $this->setAsGlobal();
        }
    }

    /**
     * (non-PHPdoc)
     * @see \Micro\Container\ContainerInterface::get()
     * @param $service
     * @return mixed
     * @throws \Exception
     */
    public function get($service)
    {
        if (isset($this->aliases[$service])) {
            $service = $this->resolveAlias($service);
        }

        // call resolved
        if (isset($this->resolved[$service]) || \array_key_exists($service, $this->resolved)) {
            return $this->resolved[$service];
        }

        if (!isset($this->services[$service])
            && !\array_key_exists($service, $this->services)
            && isset($this->bindings[$service])
            && !isset($this->ranBinders[$this->bindings[$service]])
            && $this->binder !== \null
        ) {

            $this->ranBinders[$method = $this->bindings[$service]] = \true;

            if (\method_exists($this->binder, $method)) {
                $this->binder->$method();
            }

            if (isset($this->resolved[$service]) || \array_key_exists($service, $this->resolved)) {
                return $this->resolved[$service];
            }
        }

        if (!isset($this->services[$service]) && !\array_key_exists($service, $this->services)) {
            throw new \InvalidArgumentException(\sprintf('[' . __METHOD__ . '] Service "%s" not found!', $service), 500);
        }

        $result = $this->services[$service];

        if ($result instanceof \Closure) {
            $result = $result($this);
        }

        if (\is_string($result) && \class_exists($result, \true)) {
            $result = new $result;
        }

        if ($result instanceof ContainerFactoryInterface) {
            $result = $result->create($this, $service);
        }

        if ($result instanceof ContainerAwareInterface) {
            $result->setContainer($this);
        }

        $this->resolved[$service] = $result;

        return $result;
    }

    /**
     * (non-PHPdoc)
     * @see \Micro\Container\ContainerInterface::set()
     * @param $service
     * @param $callback
     * @param bool $override
     * @return $this
     */
    public function set($service, $callback, $override = \true)
    {
        if (isset($this->resolved[$service]) || \array_key_exists($service, $this->resolved)) {
            throw new \InvalidArgumentException(\sprintf('[' . __METHOD__ . '] Service "%s" is resolved!', $service), 500);
        }

        if ((isset($this->services[$service]) || \array_key_exists($service, $this->services)) && $override === \false) {
            return $this;
        }

        $this->services[$service] = $callback;

        return $this;
    }

    /**
     * (non-PHPdoc)
     * @see \Micro\Container\ContainerInterface::has()
     * @param $service
     * @return bool
     * @throws \Exception
     */
    public function has($service)
    {
        if (isset($this->aliases[$service])) {
            $service = $this->resolveAlias($service);
        }

        return isset($this->services[$service]) || isset($this->bindings[$service]);
    }

    /**
     * @param string $alias
     * @param string $service
     * @return Container
     */
    public function alias($alias, $service)
    {
        $this->aliases[$alias] = $service;

        return $this;
    }

    /**
     * @param string $alias
     * @throws \Exception
     * @return string
     */
    public function resolveAlias($alias)
    {
        $stack = [];

        while (isset($this->aliases[$alias])) {

            if (isset($stack[$alias])) {
                throw new \Exception(\sprintf(
                    'Circular alias reference: %s -> %s',
                    \implode(' -> ', $stack),
                    $alias
                ));
            }

            $stack[$alias] = $alias;

            $alias = $this->aliases[$alias];
        }

        return $alias;
    }

    public function setBindings($binder, array $bindings)
    {
        $this->binder = $binder;
        $this->bindings = $bindings;
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetGet()
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetSet()
     * @param mixed $offset
     * @param mixed $value
     * @return Container|void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetExists()
     * @param mixed $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetUnset()
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
    }
}