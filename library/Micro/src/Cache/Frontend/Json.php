<?php

namespace Micro\Cache\Frontend;

use Micro\Cache\Core;
use Micro\Cache\Cache;

class Json extends Core
{
    public function save($data, $id = \null, $tags = [], $specificLifetime = \false, $priority = 8)
    {
        if (!$this->_options['caching']) {
            return true;
        }

        $data = \json_encode($data);

        if ($id === \null) {
            $id = $this->_lastId;
        } else {
            $id = $this->_id($id);
        }

        self::_validateIdOrTag($id);
        self::_validateTagsArray($tags);

        // automatic cleaning
        if ($this->_options['automatic_cleaning_factor'] > 0) {
            $rand = \rand(1, $this->_options['automatic_cleaning_factor']);
            if ($rand == 1) {
                //  new way                 || deprecated way
                if ($this->_extendedBackend || \method_exists($this->_backend, 'isAutomaticCleaningAvailable')) {
                    $this->clean(Cache::CLEANING_MODE_OLD);
                }
            }
        }

        if ($this->_options['ignore_user_abort']) {
            $abort = \ignore_user_abort(\true);
        }

        if (($this->_extendedBackend) && ($this->_backendCapabilities['priority'])) {
            $result = $this->_backend->save($data, $id, $tags, $specificLifetime, $priority);
        } else {
            $result = $this->_backend->save($data, $id, $tags, $specificLifetime);
        }

        if ($this->_options['ignore_user_abort']) {
            \ignore_user_abort($abort);
        }

        if (!$result) {
            // maybe the cache is corrupted, so we remove it !
            $this->_backend->remove($id);
            return \false;
        }

        if ($this->_options['write_control']) {
            $data2 = $this->_backend->load($id, \true);
            if ($data != $data2) {
                $this->_backend->remove($id);
                return \false;
            }
        }

        return \true;
    }

    public function load($id, $doNotTestCacheValidity = \false, $doNotUnserialize = \false)
    {
        if (!$this->_options['caching']) {
            return \false;
        }

        $id = $this->_id($id); // cache id may need prefix

        $this->_lastId = $id;

        self::_validateIdOrTag($id);

        $data = $this->_backend->load($id, $doNotTestCacheValidity);

        if ($data === \false) {
            // no cache available
            return \false;
        }

        return \json_decode($data, \true);
    }
}
