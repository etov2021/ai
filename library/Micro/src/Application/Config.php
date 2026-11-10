<?php

namespace Micro\Application;

class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var boolean
     */
    protected $cacheable = \true;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param array $data
     * @param boolean $cacheable
     */
    public function __construct($data = \null, $cacheable = \true)
    {
        if ($data !== \null) {
            $this->load($data);
        }

        $this->cacheable = (bool) $cacheable;
    }

    /**
     * @param array $data
     * @return Config
     */
    public function load(array $data)
    {
        $this->config = Utils::merge(
            $this->config,
            $data
        );

        return $this;
    }

    /**
     * @param string $prop
     * @param mixed $default
     * @return mixed
     */
    public function get($prop = \null, $default = \null)
    {
        $config = $this->config;

        if ($prop !== \null && \is_string($prop)) {

            if ($this->cacheable && (isset($this->cache[$prop]) || \array_key_exists($prop, $this->cache))) {
                return $this->cache[$prop];
            }

            if (\strpos($prop, '.') === \false) {
                $config = isset($config[$prop]) || \array_key_exists($prop, $config) ? $config[$prop] : $default;
                if ($this->cacheable) {
                    $this->cache[$prop] = $config;
                }
                return $config;
            }

            $parts = \explode('.', $prop);

            foreach ($parts as $part) {
                if (!\is_array($config) || (!isset($config[$part]) && !\array_key_exists($part, $config))) {
                    if ($this->cacheable) {
                        $this->cache[$prop] = $default;
                    }
                    return $default;
                }
                $config = &$config[$part];
            }

            if ($this->cacheable) {
                $this->cache[$prop] = $config;
            }
        }

        return $config;
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetExists()
     * @param mixed $offset
     * @return bool|void
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
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
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
    }

    /**
     * (non-PHPdoc)
     * @see ArrayAccess::offsetUnset()
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if ($this->cacheable) {
            unset($this->cache[$offset]);
        }
    }
}