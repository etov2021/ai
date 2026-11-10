<?php

namespace Micro\Auth\Storage;

interface StorageInterface
{
    /**
     * Returns true if and only if storage is empty
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Returns the contents of storage
     *
     * Behavior is undefined when storage is empty.
     *
     * @return mixed
     */
    public function read();

    /**
     * Writes $contents to storage
     *
     * @param  mixed $contents
     * @return void
     */
    public function write($contents);

    /**
     * Clears contents from storage
     *
     * @return void
     */
    public function clear();

    /**
     * Storage name
     *
     * @return string
     */
    public function getName();
}