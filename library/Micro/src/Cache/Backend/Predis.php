<?php

namespace Micro\Cache\Backend;

use Micro\Cache\Cache;
use Micro\Cache\Exception;
use Predis\Client as PredisClient;

class Predis extends BackendAbstract implements BackendExtendedInterface
{
    protected $_options = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'persistent' => false,
        'database' => 0,
        'auth' => null,
        'key_prefix' => 'predis_',
    ];

    /**
     * @var PredisClient
     */
    protected $_redis = null;

    /**
     * Constructor
     *
     * @param array $options associative array of options
     * @throws Exception
     * @return void
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->_redis = new PredisClient($this->_options);

        try {
            $this->_redis->connect();
            if (isset($this->_options['auth'])) {
                $this->_redis->auth($this->_options['auth']);
            }
            if (isset($this->_options['database'])) {
                $this->_redis->select($this->_options['database']);
            }
        } catch (\Throwable $e) {
            $this->_redis = null;
        }
    }

    /**
     * Returns status on if cache backend is connected to Redis
     *
     * @return bool true if cache backend is connected to Redis server.
     */
    public function isConnected()
    {
        if ($this->_redis) {
            return true;
        }

        return false;
    }


    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param string $id cache id
     * @param boolean $doNotTestCacheValidity Redis GET always acts as if $doNotTestCacheValidity is true.
     * @return string|false cached datas
     */
    public function load($id, $doNotTestCacheValidity = true)
    {
        return $this->_load($id);
    }

    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id cache id
     * @return mixed false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    public function test($id)
    {
        return $this->_test($id, false);
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  mixed  $tags             Array of strings, the cache record will be tagged by each string entry, if false, key
     *                                  can only be read if $doNotTestCacheValidity is true
     * @param  int    $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = [], $specificLifetime = false)
    {
        if (!$this->_redis) {
            return false;
        }

        if (is_string($tags)) {
            $tags = [$tags];
        }

        $lifetime = $this->getLifetime($specificLifetime);

        $keyFromItemTags = $this->_keyFromItemTags($id);

        $keyFromId = $this->_keyFromId($id);

        // If no tags were provided, just set the value and return as quickly as possible
        if (empty($tags)) {
            $this->_redis->del($keyFromItemTags); // Wipe out any existing tags for this id
            if ($lifetime === null) {
                $return = $this->_redis->set($keyFromId, $data);
            } else {
                $return = $this->_redis->setex($keyFromId, $lifetime, $data);
            }
            return $return;
        }

        // Before we enter "multi" mode, grab a list of tag TTLs.
        // Simulating behavior of redis 2.8 (-1 for no expiration, -2 for nonexistent key)
        $tagTTLs = [];

        foreach ($tags as $tag) {

            if (empty($tag)) {
                continue;
            }

            $keyFromTag = $this->_keyFromTag($tag);

            $ttl = $this->_redis->ttl($keyFromTag);

            if ($ttl == -1 && !$this->_redis->exists($keyFromTag)) {
                $ttl = -2;
            }

            $tagTTLs[$tag] = $ttl;
        }

        // Enter "multi" mode
        $this->_redis->multi();

        if ($lifetime === null) {
            $this->_redis->set($keyFromId, $data);
        } else {
            $this->_redis->setex($keyFromId, $lifetime, $data);
        }

        $itemTags = [$keyFromItemTags];

        foreach ($tags as $tag) {

            if (empty($tag)) {
                continue;
            }

            $keyFromTag = $this->_keyFromTag($tag);

            // Keep track of the tags we will add to this item's list
            $itemTags[] = $tag;

            // Add this id to the tag's list of entries
            $this->_redis->sAdd($keyFromTag, $id);

            // For keys not already set to no expiration, make sure the TTL is correct
            $ttl = $tagTTLs[$tag];

            if ($ttl != -1) {
                if ($lifetime === null && $ttl < 0) {
                    $this->_redis->persist($keyFromTag);
                } elseif ($lifetime !== null && $ttl < $lifetime) {
                    $this->_redis->expire($keyFromTag, $lifetime);
                }
            }

        }

        $this->_redis->del($keyFromItemTags);
        if (count($itemTags) > 1) {
            call_user_func_array([$this->_redis, 'sAdd'], $itemTags);
        }

        if ($lifetime !== null) {
            $this->_redis->expire($keyFromItemTags, $lifetime);
        } else {
            $this->_redis->persist($keyFromItemTags);
        }

        $return = $this->_redis->exec();

        // If return is empty or contains any individual failures, return false
        return count($return) ? !in_array(false, $return, /* strict */ true) : false;
    }

    /**
     * Save some string datas into a cache record. Only the specific key will be stored and no tags.
     * Can only be read by load() if $doNotTestCacheValidity is true
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  int    $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean true if no problem
     */
    protected function _storeKey($data, $id, $specificLifetime = false)
    {
        if (!$this->_redis) {
            return false;
        }

        $lifetime = $this->getLifetime($specificLifetime);

        if ($lifetime === null) {
            return $this->_redis->set($this->_keyFromId($id), $data);
        } else {
            return $this->_redis->setex($this->_keyFromId($id), $lifetime, $data);
        }
    }

    /**
     * Prefixes key ID
     *
     * @param string $id cache key
     * @return string prefixed id
     */
    protected function _keyFromId($id)
    {
        return $this->_options['key_prefix'] . 'item__' . $id;
    }

    /**
     * Prefixes tag ID
     *
     * @param string $id tag key
     * @return string prefixed tag
     */
    protected function _keyFromTag($id)
    {
        return $this->_options['key_prefix'] . 'tag__' . $id;
    }

    /**
     * Prefixes item tag ID
     *
     * @param string $id item tag key
     * @return string prefixed item tag
     */
    protected function _keyFromItemTags($id)
    {
        return $this->_options['key_prefix'] . 'item_tags__' . $id;
    }

    /**
     * Remove a cache record
     *
     * @param  string $id cache id
     * @return boolean true if no problem
     */
    public function remove($id)
    {
        if (!$this->_redis) {
            return false;
        }

        if (!$id) {
            return false;
        }

        if (is_string($id)) {
            $id = [$id];
        }

        if (!count($id)) {
            return false;
        }

        $deleteIds = [];

        foreach ($id as $i) {
            $deleteIds[] = $this->_keyFromItemTags($i);
            $deleteIds[] = $this->_keyFromId($i);
        }

        $this->_redis->del($deleteIds);

        return true;
    }


    /**
     * Remove a cache tag record
     *
     * @param  string $tag cache tag
     * @return boolean true if no problem
     */
    public function removeTag($tag)
    {
        if (!$this->_redis) {
            return false;
        }

        if (is_string($tag)) {
            $tag = [$tag];
        }

        if (empty($tag)) {
            return false;
        }

        $deleteTags = [];

        foreach ($tag as $t) {
            $deleteTags[] = $this->_keyFromTag($t);
        }

        if ($deleteTags && count($deleteTags)) {
            $this->_redis->del($deleteTags);
        }

        return true;
    }

    /**
     * Returns wether a specific member key exists in the Redis set
     *
     * @param string $member
     * @param string $set
     * @return bool true or false
     */
    public function existsInSet($member, $set)
    {
        if (!$this->_redis) {
            return null;
        }

        if (!$this->_redis->sIsMember($this->_keyFromId($set), $member)) {
            return false;
        }

        return true;
    }

    /**
     * Adds a key to a set
     *
     * @param mixed $member key(s) to add
     * @param string $set
     * @param string $specificLifetime lifetime, null for persistant
     * @return bool result of the add
     */
    public function addToSet($member, $set, $specificLifetime = false)
    {
        if (!$this->_redis) {
            return null;
        }

        $lifetime = $this->getLifetime($specificLifetime);

        if (is_array($member)) {
            $redis = $this->_redis;
            $return = call_user_func_array([$redis, 'sAdd'], array_merge([$this->_keyFromId($set)], $member));
        } else {
            $return = $this->_redis->sAdd($this->_keyFromId($set), $member);
        }

        if ($lifetime !== null) {
            $this->_redis->expire($this->_keyFromId($set), $lifetime);
        }

        return $return;
    }

    /**
     * Removes a key from a redis set.
     *
     * @param mixed $member key(s) to remove
     * @param string $set
     * @return bool result of removal
     */
    public function removeFromSet($member, $set)
    {
        if (!$this->_redis) {
            return null;
        }

        if (is_array($member)) {
            if (!count($member)) {
                return true;
            }
            $redis = $this->_redis;
            $return = call_user_func_array([$redis, 'sRem'], array_merge([$this->_keyFromId($set)], $member));
        } else {
            $return = $this->_redis->sRem($this->_keyFromId($set), $member);
        }

        return $return;
    }

    /**
     * Returns all keys in a Redis set
     *
     * @param string $set
     * @return array member keys of set
     */
    public function membersInSet($set)
    {
        if (!$this->_redis) {
            return null;
        }

        return $this->_redis->sMembers($this->_keyFromId($set));
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     *
     * Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param string $mode clean mode
     * @param tags array $tags array of tags
     * @return boolean true if no problem
     */
    public function clean($mode = Cache::CLEANING_MODE_ALL, $tags = [])
    {
        return $this->_clean($mode, $tags);
    }

    /**
     * Clean some cache records (protected method used for recursive stuff)
     *
     * Available modes are :
     * Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $dir  Directory to clean
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @throws Exception
     * @return boolean True if no problem
     */
    protected function _clean($mode = Cache::CLEANING_MODE_ALL, $tags = [])
    {
        if (!$this->_redis) {
            return false;
        }

        if ($mode == Cache::CLEANING_MODE_ALL) {
            return $this->_redis->flushDb();
        }

        if ($mode == Cache::CLEANING_MODE_OLD) {
            return true; /* Redis takes care of expire */
        }

        if ($mode == Cache::CLEANING_MODE_MATCHING_ANY_TAG && $tags && (is_string($tags) || count($tags))) {
            if (is_string($tags)) {
                $tags = [$tags];
            }
            $idsToRemove = [];
            foreach ($tags as $tag) {
                $idsToRemove = array_merge($idsToRemove, $this->getIdsMatchingTags($tag));
            }
            return $this->remove($idsToRemove);
        }

        if ($mode == Cache::CLEANING_MODE_MATCHING_TAG && $tags && (is_string($tags) || count($tags))) {
            return $this->remove($this->getIdsMatchingTags($tags));
        }

        if ($mode == Cache::CLEANING_MODE_NOT_MATCHING_TAG) {
            Cache::throwException('CLEANING_MODE_NOT_MATCHING_TAG not implemented for Redis cache');
        }

        Cache::throwException('Invalid mode for clean() method');
    }

    /**
     * Test if the given cache id is available (and still valid as a cache record)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return boolean|mixed false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    protected function _test($id, $doNotTestCacheValidity)
    {
        if (!$this->_redis) {
            return false;
        }

        if ($doNotTestCacheValidity) {
            return true;
        }

        $tags = $this->_redis->sMembers($this->_keyFromItemTags($id));

        if (!$tags || !count($tags)) {
            return false;
        }

        foreach ($tags as $tag) {
            if ($tag && !$this->_redis->sIsMember($this->_keyFromTag($tag), $id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return cached id
     *
     * @param string $id cache id
     * @return string|bool cached data, or FALSE if cache has no value for $id
     */
    protected function _load($id)
    {
        if (!$this->_redis) {
            return false;
        }

        $result = $this->_redis->get($this->_keyFromId($id));

        return $result ?: false;
    }

    /**
     * Return an array of stored cache ids. Not implemented for Redis cache
     *
     * @throws Exception
     */
    public function getIds()
    {
        Cache::throwException('Not possible to get available IDs on Redis cache');
    }


    /**
     * Return an array of stored tags. Not implemented for Redis cache
     *
     * @throws Exception
     */
    public function getTags()
    {
        Cache::throwException('Not possible to get available tags on Redis cache');
    }

    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function getIdsMatchingTags($tags = [])
    {
        if (!$this->_redis) {
            return [];
        }

        if (!$tags) {
            return [];
        }

        if ($tags && is_string($tags)) {
            $tags = [$tags];
        }

        $matchTags = [];
        foreach ($tags as $tag) {
            $matchTags[] = $this->_keyFromTag($tag);
        }

        if (count($matchTags) == 1) {
            return $this->_redis->sMembers($matchTags[0]);
        }

        return $this->_redis->sInter($matchTags);
    }

    /**
     * Return an array of stored cache ids which don't match given tags. Not implemented for Redis cache
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @throws Exception
     */
    public function getIdsNotMatchingTags($tags = [])
    {
        Cache::throwException('Not possible to get IDs not matching tags on Redis cache');
    }

    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function getIdsMatchingAnyTags($tags = [])
    {
        if (!$this->_redis) {
            return [];
        }

        if (!$tags) {
            return [];
        }

        if ($tags && is_string($tags)) {
            $tags = [$tags];
        }

        $return = [];

        foreach ($tags as $tag) {
            foreach ($this->_redis->sMembers($this->_keyFromTag($tag)) as $id) {
                $return[] = $id;
            }
        }

        return $return;
    }

    /**
     * Return the filling percentage of the backend storage. Not implemented on Redis cache
     *
     * @throws Exception
     */
    public function getFillingPercentage()
    {
        Cache::throwException('getFillingPercentage not implemented on Redis cache');
    }

    /**
     * Return an array of metadatas for the given cache id. Not implemented on Redis cache
     *
     * @param string $id cache id
     * @throws Exception
     */
    public function getMetadatas($id)
    {
        Cache::throwException('Metadata not implemented on Redis cache');
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extraLifetime)
    {
        if (!$this->_redis) {
            return false;
        }

        $tags = $this->_redis->sMembers($this->_keyFromItemTags($id));

        $lifetime = $this->getLifetime($extraLifetime);

        $return = false;

        if ($lifetime !== null) {
            $this->_redis->expire($this->_keyFromItemTags($id), $lifetime);
            $return = $this->_redis->expire($this->_keyFromId($id), $lifetime);
        } else {
            $this->_redis->persist($this->_keyFromItemTags($id));
            $return = $this->_redis->persist($this->_keyFromId($id));
        }

        if ($tags) {
            foreach ($tags as $tag) {
                if ($tag) {
                    $ttl = $this->_redis->ttl($this->_keyFromTag($tag));
                    if ($ttl !== false && $ttl !== -1 && $ttl < $lifetime && $lifetime !== null) {
                        $this->_redis->expire($this->_keyFromTag($tag), $lifetime);
                    } else if ($ttl !== false && $ttl !== -1 && $lifetime === null) {
                        $this->_redis->persist($this->_keyFromTag($tag));
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id (and only that key, no tags are updated)
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    protected function _touchKey($id, $extraLifetime)
    {
        if (!$this->_redis) {
            return false;
        }

        $data = $this->load($id, true);

        if ($data === false) {
            return false;
        }

        return $this->_storeKey($data, $id, $extraLifetime);
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * The array must include these keys :
     * - automatic_cleaning (is automating cleaning necessary)
     * - tags (are tags supported)
     * - expired_read (is it possible to read expired cache records
     *                 (for doNotTestCacheValidity option for example))
     * - priority does the backend deal with priority when saving
     * - infinite_lifetime (is infinite lifetime can work with this backend)
     * - get_list (is it possible to get the list of cache ids and the complete list of tags)
     *
     * @return array associative of with capabilities
     */
    public function getCapabilities()
    {
        return [
            'automatic_cleaning' => true,
            'tags' => true,
            'expired_read' => false,
            'priority' => false,
            'infinite_lifetime' => true,
            'get_list' => false
        ];
    }
}