<?php

namespace Micro\Cache;

abstract class Cache
{
    /**
     * Standard frontends
     *
     * @var array
     */
    public static $standardFrontends = array('Core');

    /**
     * Standard backends
     *
     * @var array
     */
    public static $standardBackends = array('File', 'Apc', 'Memcached', 'Libmemcached', 'Sqlite');

    /**
     * Standard backends which implement the ExtendedInterface
     *
     * @var array
     */
    public static $standardExtendedBackends = array('File', 'Apc', 'Memcached', 'Libmemcached', 'Sqlite');

    /**
     * Only for backward compatibily (may be removed in next major release)
     *
     * @var array
     * @deprecated
     */
    public static $availableFrontends = array('Core');

    /**
     * Only for backward compatibily (may be removed in next major release)
     *
     * @var array
     * @deprecated
     */
    public static $availableBackends = array('File', 'Apc', 'Memcached', 'Libmemcached', 'Sqlite');

    /**
     * @var array
     */
    public static $adapters = [];

    /**
     * @var array
     */
    public static $caches = [];

    /**
     * Consts for clean() method
     */
    const CLEANING_MODE_ALL              = 'all';
    const CLEANING_MODE_OLD              = 'old';
    const CLEANING_MODE_MATCHING_TAG     = 'matchingTag';
    const CLEANING_MODE_NOT_MATCHING_TAG = 'notMatchingTag';
    const CLEANING_MODE_MATCHING_ANY_TAG = 'matchingAnyTag';

    public static function factory($frontend, $backend, $frontendOptions = array(), $backendOptions = array(), $customFrontendNaming = false, $customBackendNaming = false)
    {
        $frontendObject = null;
        $backendObject = null;

        if (is_string($backend)) {
            $backendObject = self::_makeBackend($backend, $backendOptions, $customBackendNaming);
        } else {
            if ((is_object($backend)) && (in_array('Micro\Cache\Backend\BackendInterface', class_implements($backend)))) {
                $backendObject = $backend;
            } else {
                self::throwException('backend must be a backend name (string) or an object which implements Micro\Cache\Backend\BackendInterface');
            }
        }
        if (is_string($frontend)) {
            $frontendObject = self::_makeFrontend($frontend, $frontendOptions, $customFrontendNaming);
        } else {
            if (is_object($frontend)) {
                $frontendObject = $frontend;
            } else {
                self::throwException('frontend must be a frontend name (string) or an object');
            }
        }
        $frontendObject->setBackend($backendObject);
        return $frontendObject;
    }

    /**
     * Frontend Constructor
     *
     * @param string  $backend
     * @param array   $backendOptions
     * @param boolean $customBackendNaming
     * @return \Micro\Cache\Backend\BackendInterface
     */
    public static function _makeBackend($backend, $backendOptions, $customBackendNaming = false)
    {
        if (!$customBackendNaming) {
            $backend  = self::_normalizeName($backend);
        }
        if (in_array($backend, self::$standardBackends)) {
            // we use a standard backend
            $backendClass = 'Micro\Cache\Backend\\' . $backend;
        } else {
            // we use a custom backend
            if (!preg_match('~^[\w\\\]+$~D', $backend)) {
                self::throwException("Invalid backend name [$backend]");
            }
            if (!$customBackendNaming) {
                // we use this boolean to avoid an API break
                $backendClass = 'Micro\Cache\Backend\\' . $backend;
            } else {
                $backendClass = $backend;
            }
        }
        if (!class_exists($backendClass)) {
            self::throwException("Invalid backend class [$backendClass]");
        }
        return new $backendClass($backendOptions);
    }

    /**
     * Backend Constructor
     *
     * @param string  $frontend
     * @param array   $frontendOptions
     * @param boolean $customFrontendNaming
     * @return \Micro\Cache\Core
     */
    public static function _makeFrontend($frontend, $frontendOptions = array(), $customFrontendNaming = false)
    {
        if (!$customFrontendNaming) {
            $frontend = self::_normalizeName($frontend);
        }
        if (in_array($frontend, self::$standardFrontends)) {
            // we use a standard frontend
            // For perfs reasons, with frontend == 'Core', we can interact with the Core itself
            $frontendClass = 'Micro\Cache\\' . ($frontend != 'Core' ? 'Frontend\\' : '') . $frontend;
        } else {
            // we use a custom frontend
            if (!preg_match('~^[\w\\\]+$~D', $frontend)) {
                self::throwException("Invalid frontend name [$frontend]");
            }
            if (!$customFrontendNaming) {
                // we use this boolean to avoid an API break
                $frontendClass = 'Micro\Cache\Frontend\\' . $frontend;
            } else {
                $frontendClass = $frontend;
            }
        }
        if (!class_exists($frontendClass)) {
            self::throwException("Invalid frontend class [$frontendClass]");
        }
        return new $frontendClass($frontendOptions);
    }

    /**
     * @param $msg
     * @param \Exception|null $e
     * @throws Exception
     */
    public static function throwException($msg, ?\Exception $e = null)
    {
        throw new Exception($msg, 0, $e);
    }

    /**
     * Normalize frontend and backend names to allow multiple words TitleCased
     *
     * @param  string $name  Name to normalize
     * @return string
     */
    protected static function _normalizeName($name)
    {
        $name = ucfirst(strtolower($name));
        $name = str_replace(array('-', '_', '.'), ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

    /**
     * @param string $key
     * @return string
     */
    public static function normalizeKey($key)
    {
        return preg_replace('~[^a-zA-Z0-9_]+~', '_', $key);
    }

    /**
     * @param array $adapters
     */
    public static function register(array $adapters)
    {
        static::$adapters = $adapters;
    }

    /**
     * @param string $adapter
     * @return Core
     */
    public static function load($adapter)
    {
        if (!isset(static::$adapters[$adapter])) {
            return null;
        }

        if (!isset(static::$caches[$adapter])) {
            $adapterConfig = static::$adapters[$adapter];
            static::$caches[$adapter] = static::factory(
                $adapterConfig['frontend']['adapter'], $adapterConfig['backend']['adapter'],
                $adapterConfig['frontend']['options'], $adapterConfig['backend']['options']
            );
        }

        return static::$caches[$adapter];
    }

    /**
     * @return array
     */
    public static function loadAll()
    {
        foreach (static::$adapters as $adapter => $config) {
            static::load($adapter);
        }

        $loadAllExclude = [];
        if (is_callable('config')) {
            $loadAllExclude = config('cache.loadAllExclude', []);
        }

        $caches = [];
        foreach (static::$caches as $k => $cache) {
            if (in_array($k, $loadAllExclude)) {
                continue;
            }
            $caches[$k] = $cache;
        }

        return $caches;
    }
}