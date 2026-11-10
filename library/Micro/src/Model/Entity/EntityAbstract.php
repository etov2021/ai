<?php

namespace Micro\Model\Entity;

abstract class EntityAbstract implements EntityInterface
{
    public function setFromArray(array $data)
    {
        foreach ($data as $k => $v) {
            $method = 'set' . \ucfirst($k);
            if (\method_exists($this, $method)) {
                $this->$method($v);
            } else if (\property_exists($this, $k)) {
                $this->$k = $v;
            }
        }

        return $this;
    }

    public function toArray()
    {
        /* $data = [];

        $r = new \ReflectionClass($this);
        foreach ($r->getProperties() as $p) {
            if ($p instanceof \ReflectionProperty) {
                $p->setAccessible(true);
                $data[$p->getName()] = $p->getValue($this);
            }
        } */

        $data = [];

        foreach ($this as $k => $v) {
            $data[$k] = $v;
        }

        return $data;
    }

    public function toJson()
    {
        return \json_encode($this->toArray());
    }

    public function toLog()
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return \get_class($this);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists ($offset)
    {
        return \property_exists($this, $offset);
    }

    /**
     * @param mixed $offset
     * @return \null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet ($offset)
    {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet ($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset ($offset)
    {
        $this->$offset = \null;
    }

    /**
     * @param string $offset
     * @return \null
     */
    public function __get($offset)
    {
        return $this->$offset;
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function __isset($offset)
    {
        return \property_exists($this, $offset);
    }

    /**
     * @param string $offset
     */
    public function __unset($offset)
    {
        $this->$offset = \null;
    }
}