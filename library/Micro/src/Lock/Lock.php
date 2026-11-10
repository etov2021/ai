<?php

namespace Micro\Lock;

class Lock
{
    const EXCLUSIVE = true;
    const SHARED = false;

    const BLOCKING = true;
    const NON_BLOCKING = false;

    private $lockFile;
    private $timeout;
    private $exclusive;
    private $blocking;
    private $fh;
    private $removeOnRelease;

    /**
     * @param string          $lockFile          path to file
     * @param int             $timeout           path to file
     * @param boolean         $exclusive         true for an exclusive lock, false for shared one
     * @param boolean         $blocking          true to wait for lock to be available,
     *                                           false to throw exception instead of waiting
     * @param boolean         $removeOnRelease   remove file on release if no other lock remains
     */
    public function __construct(
        $lockFile,
        $timeout = 30,
        $exclusive = Lock::EXCLUSIVE,
        $blocking = Lock::NON_BLOCKING,
        $removeOnRelease = false
    ) {
        $dirName = dirname($lockFile);
        if (!is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }
        $this->lockFile        = $lockFile;
        $this->timeout         = (int) $timeout;
        $this->exclusive       = $exclusive;
        $this->blocking        = $blocking;
        $this->removeOnRelease = $removeOnRelease;
    }

    public function acquire()
    {
        if ($this->exclusive === Lock::EXCLUSIVE) {
            $lockType = "exclusive";
            $operation = LOCK_EX;
        } else {
            $lockType = "shared";
            $operation = LOCK_SH;
        }

        if ($this->blocking === Lock::NON_BLOCKING) {
            $operation |= LOCK_NB;
        }

        $this->tryAcquire($operation, $lockType);
    }

    /**
     * try to acquire lock on file, throw in case of faillure
     * @param  int    $operation
     * @param  string $lockType  lock type description
     * @return void
     * @see https://php.net/flock
     */
    private function tryAcquire($operation, $lockType)
    {
        $expiryTime = time() + $this->timeout;
        while (!$this->flock($operation)) {
            if ($this->timeout && time() >= $expiryTime) {
                break;
            }
            usleep(250 * 1000);
        }
    }

    public function release()
    {
        if ($this->fh === null) {
            return;
        }

        $this->flock(LOCK_UN);
        fclose($this->fh);

        if ($this->removeOnRelease) {
            if (is_file($this->lockFile)) {
                unlink($this->lockFile);
            }
        }

        $this->fh = null;
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * @return boolean
     */
    private function flock($operation)
    {
        if ($this->fh === null) {
            $this->fh = fopen($this->lockFile, "c");
        }

        if (!is_resource($this->fh)) {
            throw new \RuntimeException("Could not open lock file {$this->lockFile}");
        }

        return flock($this->fh, $operation);
    }
}