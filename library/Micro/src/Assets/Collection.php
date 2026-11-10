<?php

namespace Micro\Assets;

class Collection
{
    protected $collections = [];

    protected $options = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        if (isset($this->options['bundle'])) {

            $bundleOptions = $this->options['bundle'];

            unset($this->options['bundle']);

            foreach ($bundleOptions as $name => $options) {
                if (is_int($name)) {
                    continue;
                }
                $this->create($name, $options);
            }
        }
    }

    /**
     * @param string $name
     * @param array $options
     * @return Bundle
     */
    public function create($name, array $options = [])
    {
        if (!isset($this->collections[$name])) {
            $this->collections[$name] = new Bundle($name, $this->options);
        }

        if (!empty($options)) {
            $this->collections[$name]->configure($options);
        }

        return $this->collections[$name];
    }

    /**
     * @param string|array|null $name
     * @param array $attributes
     * @return string
     */
    public function js($name = null, array $attributes = [])
    {
        if ($name === null) {
            $collections = $this->collections;
        } else if (is_array($name)) {
            $collections = [];
            foreach ($name as $n) {
                $collections[$n] = $this->create($n);
            }
        } else {
            $collections = [$name => $this->create($name)];
        }

        $buff = '';

        foreach ($collections as $bundle) {
            $buff .= $bundle->js($attributes);
        }

        return $buff;
    }

    /**
     * @param string|array|null $name
     * @param array $attributes
     * @return string
     */
    public function css($name = null, array $attributes = [])
    {
        if ($name === null) {
            $collections = $this->collections;
        } else if (is_array($name)) {
            $collections = [];
            foreach ($name as $n) {
                $collections[$n] = $this->create($n);
            }
        } else {
            $collections = [$name => $this->create($name)];
        }

        $buff = '';

        foreach ($collections as $bundle) {
            $buff .= $bundle->css($attributes);
        }

        return $buff;
    }
}