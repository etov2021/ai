<?php

namespace Micro\Auth;

class Auth
{
    /**
     * @var Auth
     */
    protected static $instance;

    /**
     * @var Storage\StorageInterface
     */
    protected $storage;

    /**
     * @var callable
     */
    protected static $resolver;

    protected static $cache = \false;

    protected function __construct() {}

    protected function __clone() {}

    public function getStorage()
    {
        if ($this->storage === \null) {
            $this->setStorage(new Storage\Session());
        }

        return $this->storage;
    }

    /**
     * @param Storage\StorageInterface $storage
     * @return Auth
     */
    public function setStorage(Storage\StorageInterface $storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Authenticates against the supplied adapter
     *
     * @param  Adapter\AdapterInterface $adapter
     * @return int
     */
    public function authenticate(Adapter\AdapterInterface $adapter)
    {
        $result = $adapter->authenticate();

        if ($this->hasIdentity()) {
            $this->clearIdentity();
        }

        if ($result) {
            $this->setIdentity($result);
        }

        return $result;
    }

    /**
     * @return Auth
     */
    public static function getInstance()
    {
        if (static::$instance === \null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * @param mixed $identity
     * @return Auth
     */
    public function setIdentity($identity)
    {
        $this->getStorage()->write($identity);

        static::$cache = \false;

        return $this;
    }

    /**
     * Returns true if and only if an identity is available from storage
     *
     * @return boolean
     */
    public function hasIdentity()
    {
        return !$this->getStorage()->isEmpty();
    }

    /**
     * Returns the identity from storage or null if no identity is available
     *
     * @return mixed|null
     */
    public function getIdentity()
    {
        $storage = $this->getStorage();

        if ($storage->isEmpty()) {
            return \null;
        }

        return $storage->read();
    }

    /**
     * Clears the identity from persistent storage
     *
     * @return Auth
     */
    public function clearIdentity()
    {
        $this->getStorage()->clear();

        static::$cache = \false;

        return $this;
    }

    /**
     * @param boolean $force
     * @return mixed
     * @throws \Exception
     */
    public static function identity($force = \false)
    {
        if ($force === \false && static::$cache !== \false) {
            return static::$cache;
        }

        if (static::$resolver === \null) {
            throw new \Exception('Auth resolver is missing. Please register it. For example on Application.Start event', 500);
        }

        $identity = static::$resolver->__invoke(
            static::getInstance()->getIdentity()
        );

        if ($identity instanceof Identity) {
            return static::$cache = $identity;
        }

        return static::$cache = \null;
    }

    /**
     * @param callable $resolver
     */
    public static function setResolver(callable $resolver)
    {
        static::$resolver = $resolver;
    }
}