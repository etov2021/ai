<?php

namespace Micro\Transliterate;

abstract class TransliterateAbstract implements \ArrayAccess, \Countable, \Iterator
{
    protected $map = [];

    protected $mapReverse = [];

    /**
     * Transliterate string from and to ASCII
     *
     * @param string $string
     * @param boolean $forward
     */
    abstract public function convert($string, $forward = \true);

    /**
     * @param string $string
     * @param boolean $forward
     */
    protected function _convert($string, $forward = \true)
    {
        $arrayName = 'map';

        if (!$forward) {
            $arrayName = 'mapReverse';
            if (empty($this->$arrayName)) {
                $this->$arrayName = \array_flip($this->map);
            }
        }

        return \strtr($string, $this->$arrayName);
    }

    /**
     * ArrayAccess
     *
     * @param string $offset
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->map[$offset]);
    }

    /**
     * ArrayAccess
     *
     * @param string $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->map[$offset]) ? $this->map[$offset] : \null;
    }

    /**
     * ArrayAccess
     *
     * @param string $offset
     * @param string $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->map[$offset] = $value;
    }

    /**
     * ArrayAccess
     *
     * @param string $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->map[$offset]);
    }

    /**
     * Countable
     *
     * @return integer
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->map);
    }

    /**
     * Iterator
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return \current($this->map);
    }

    /**
     * Iterator
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return \key($this->map);
    }

    /**
     * Iterator
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        return \next($this->map);
    }

    /**
     * Iterator
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        return \reset($this->map);
    }

    /**
     * Iterator
     *
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return \key($this->map) !== null;
    }
}