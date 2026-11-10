<?php

namespace Micro\Event;

class Manager
{
    /**
     * @var array
     */
    protected $events = [];
    protected $executed = [];

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param string|array $event
     * @param callable $callable
     * @param int $priority
     * @return Manager
     */
    public function attach($event, callable $callable, $priority = 10)
    {
        if (\is_array($event)) {
            foreach ($event as $evn) {
                $this->attach($evn, $callable, $priority);
            }
            return $this;
        }

        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }

        $priority = (int) $priority;

        if (!isset($this->events[$event][$priority])) {
            $this->events[$event][$priority] = [];
        }

        $this->events[$event][$priority][] = $callable;

        return $this;
    }

    /**
     * @param string $event
     * @param array $params
     * @return mixed
     */
    public function trigger($event, array $params = [])
    {
        $listeners = $this->getListeners($event);

        if (empty($listeners)) {
            return \null;
        }

        $message = new Message($event, $params);
        $response = \null;

        foreach ($listeners as $callbacks) {

            foreach ($callbacks as $callback) {

                $response = \call_user_func($callback, $message);

                if ($message->stopped()) {
                    break 2;
                }
            }
        }

        return $response;
    }

    public function getExecuted()
    {
        return $this->executed;
    }

    /**
     * @param string $event
     */
    protected function getListeners($requestEvent)
    {
        static $listeners = [];

        // calculated listeners for requested event
        if (isset($listeners[$requestEvent])) {
            return $listeners[$requestEvent];
        }

        $eventListeners = [];

        foreach ($this->events as $event => $priorities) {

            if ($event === $requestEvent
                || (\strstr($event, '*') !== \false && \preg_match('~^' . \str_replace(['.', '*'], ['\.', '[^.]+'], $event) . '$~', $requestEvent))
            ) {

                $this->executed[$event] = true;

                foreach ($priorities as $priority => $callbacks) {
                    if (!isset($eventListeners[$priority])) {
                        $eventListeners[$priority] = $callbacks;
                    } else {
                        $eventListeners[$priority] = array_merge($eventListeners[$priority], $callbacks);
                    }
                }
            }
        }

        if (!empty($eventListeners)) {
            \krsort($eventListeners, \SORT_NUMERIC);
        }

        return $listeners[$requestEvent] = $eventListeners;
    }
}