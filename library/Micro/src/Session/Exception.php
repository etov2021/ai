<?php

namespace Micro\Session;

class Exception extends \Exception
{
    /**
     * sessionStartError
     *
     * @var string PHP Error Message
     */
    public static $sessionStartError = null;

    /**
     * handleSessionStartError() - interface for set_error_handler()
     *
     * @param  int    $errno
     * @param  string $errstr
     * @return void
     */
    static public function handleSessionStartError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        self::$sessionStartError = $errfile . '(Line:' . $errline . '): Error #' . $errno . ' ' . $errstr;
    }

    /**
     * handleSilentWriteClose() - interface for set_error_handler()
     *
     * @param  int    $errno
     * @param  string $errstr
     * @return void
     */
    static public function handleSilentWriteClose($errno, $errstr, $errfile, $errline, $errcontext)
    {
        self::$sessionStartError .= PHP_EOL . $errfile . '(Line:' . $errline . '): Error #' . $errno . ' ' . $errstr;
    }
}