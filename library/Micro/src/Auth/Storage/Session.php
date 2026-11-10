<?php

namespace Micro\Auth\Storage;

use Micro\Session\SessionNamespace;
use Micro\Application\Utils;

class Session implements StorageInterface
{
    protected $session;

    protected $namespace = 'default';

    public function __construct($namespace = 'default')
    {
        $this->namespace = $namespace;
        $this->session   = new SessionNamespace('identity');
    }

    public function getName()
    {
        return $this->namespace;
    }

    public function isEmpty()
    {
        return !isset($this->session->{$this->namespace});
    }

    public function write($contents)
    {
        $this->session->{$this->namespace} = Utils::safeSerialize($contents);
    }

    public function read()
    {
        return isset($this->session->{$this->namespace}) ? Utils::safeUnserialize($this->session->{$this->namespace}) : \null;
    }

    public function clear()
    {
        unset($this->session->{$this->namespace});
    }
}