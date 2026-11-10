<?php

namespace Micro\Event;

class Message
{
    /**
     * @var string
     */
    protected $event;

    /**
     * @var mixed
     */
    protected $params = [];

    /**
     * @var bool
     */
    protected $stopPropagation = \false;

    /**
     * @param string $event
     * @param mixed $params
     */
    public function __construct($event = \null, array $params = [])
    {
        if ($event !== \null) {
            $this->setEvent($event);
        }

        if (!empty($params)) {
            $this->setParams($params);
        }
    }

	/**
     * @return string $event
     */
    public function getEvent ()
    {
        return $this->event;
    }

	/**
     * @param string $event
     * @return Message
     */
    public function setEvent ($event)
    {
        $this->event = $event;

        return $this;
    }

	/**
     * @return mixed $params
     */
    public function getParams ()
    {
        return $this->params;
    }

    /**
     * @param $name
     * @return mixed $param
     */
    public function getParam ($name)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        return \null;
    }

	/**
     * @param mixed $params
     * @return Message
     */
    public function setParams (array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @param bool $value
     * @return Message
     */
    public function setStopPropagation($value)
    {
        $this->stopPropagation = (bool) $value;

        return $this;
    }

    /**
     * @return Message
     */
    public function stop()
    {
        $this->setStopPropagation(\true);

        return $this;
    }

    /**
     * @return bool
     */
    public function stopped()
    {
        return $this->stopPropagation;
    }

    /**
     * @param string $key
     * @return mixed|\null
     */
    public function __get($key)
    {
        return $this->getParam($key);
    }
}