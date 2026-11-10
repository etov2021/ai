<?php

namespace Micro\Container;

interface ContainerInterface extends \ArrayAccess
{
    /**
     * @param string $id
     */
    public function get($id);

    /**
     * @param string $id
     */
    public function has($id);

    /**
     * @param string $id
     * @param mixed $service
     * @param boolean $override
     */
    public function set($id, $service, $override = \true);

    /**
     * @param object $binder
     * @param array $bindings
     */
    public function setBindings($binder, array $bindings);

    /**
     * @param array $config
     */
    public function configure(array $config);
}