<?php

namespace Micro\Container;

use Micro\Exception\Exception as CoreException;

class SharedContainer
{
    /**
     * @var ContainerInterface $instance
     */
    private static $instance;

    /**
     * @param ContainerInterface $instance
     */
    public static function setInstance(ContainerInterface $instance)
    {
        static::$instance = $instance;
    }

    /**
     * @return ContainerInterface
     */
    public static function getInstance()
    {
        if (static::$instance === \null) {
            throw new CoreException("The ContainerInterface instance is not set in SharedContainer");
        }

        return static::$instance;
    }

    /**
     * Check for instance existing
     */
    public static function hasInstance()
    {
        return static::$instance !== \null;
    }

    /**
     * Reset instance
     */
    public static function resetInstance()
    {
        static::$instance = \null;
    }
}