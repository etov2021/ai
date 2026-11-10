<?php

namespace Micro\Application;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel as LogLevel;

/**
 * Micro error handler
 */
class ErrorHandler
{
    private $logger;

    private $previousExceptionHandler;
    private $uncaughtExceptionLevel;

    private $previousErrorHandler;
    private $errorLevelMap;

    private $hasFatalErrorHandler;
    private $fatalLevel;
    private $reservedMemory;

    private static $fatalErrors = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registers a new ErrorHandler for a given Logger
     *
     * By default it will handle errors, exceptions and fatal errors
     *
     * @param  Logger          $logger
     * @param  array|false     $errorLevelMap  an array of E_* constant to LogLevel::* constant mapping, or false to disable error handling
     * @param  int|false       $exceptionLevel a LogLevel::* constant, or false to disable exception handling
     * @param  int|false       $fatalLevel     a LogLevel::* constant, or false to disable fatal error handling
     * @return ErrorHandler
     */
    public static function register(Logger $logger, $errorLevelMap = [], $exceptionLevel = \null, $fatalLevel = \null)
    {
        $handler = new static($logger);
        if ($errorLevelMap !== \false) {
            $handler->registerErrorHandler($errorLevelMap);
        }
        if ($exceptionLevel !== \false) {
            $handler->registerExceptionHandler($exceptionLevel);
        }
        if ($fatalLevel !== \false) {
            $handler->registerFatalHandler($fatalLevel);
        }

        return $handler;
    }

    public function registerExceptionHandler($level = \null, $callPrevious = \true)
    {
        $prev = \set_exception_handler([$this, 'handleException']);
        $this->uncaughtExceptionLevel = $level;
        if ($callPrevious && $prev) {
            $this->previousExceptionHandler = $prev;
        }
    }

    public function registerErrorHandler(array $levelMap = [], $callPrevious = \true, $errorTypes = -1)
    {
        $prev = \set_error_handler(array($this, 'handleError'), $errorTypes);
        $this->errorLevelMap = \array_replace($this->defaultErrorLevelMap(), $levelMap);
        if ($callPrevious) {
            $this->previousErrorHandler = $prev ?: \true;
        }
    }

    public function registerFatalHandler($level = \null, $reservedMemorySize = 20)
    {
        \register_shutdown_function([$this, 'handleFatalError']);

        $this->reservedMemory = \str_repeat(' ', 1024 * $reservedMemorySize);
        $this->fatalLevel = $level;
        $this->hasFatalErrorHandler = true;
    }

    protected function defaultErrorLevelMap()
    {
        return array(
            \E_ERROR             => LogLevel::CRITICAL,
            \E_WARNING           => LogLevel::WARNING,
            \E_PARSE             => LogLevel::ALERT,
            \E_NOTICE            => LogLevel::NOTICE,
            \E_CORE_ERROR        => LogLevel::CRITICAL,
            \E_CORE_WARNING      => LogLevel::WARNING,
            \E_COMPILE_ERROR     => LogLevel::ALERT,
            \E_COMPILE_WARNING   => LogLevel::WARNING,
            \E_USER_ERROR        => LogLevel::ERROR,
            \E_USER_WARNING      => LogLevel::WARNING,
            \E_USER_NOTICE       => LogLevel::NOTICE,
            2048                 => LogLevel::NOTICE, // E_STRICT (е остаряла от PHP 8.4)
            \E_RECOVERABLE_ERROR => LogLevel::ERROR,
            \E_DEPRECATED        => LogLevel::NOTICE,
            \E_USER_DEPRECATED   => LogLevel::NOTICE,
        );
    }

    /**
     * @private
     * @param \Exception $e
     */
    public function handleException($e)
    {
        $this->logger->log(
            $this->uncaughtExceptionLevel === \null ? LogLevel::ERROR : $this->uncaughtExceptionLevel,
            \sprintf('Uncaught Exception %s: "%s" at %s line %s', \get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            ['exception' => $e, 'type' => 'exceptions']
        );

        if ($this->previousExceptionHandler) {
            \call_user_func($this->previousExceptionHandler, $e);
        }

        exit(255);
    }

    /**
     * @private
     * @param $code
     * @param $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return bool|void
     */
    public function handleError($code, $message, $file = '', $line = 0, $context = [])
    {
        if (!(\error_reporting() & $code)) {
            return \null;
        }

        // fatal error codes are ignored if a fatal error handler is present as well to avoid duplicate log entries
        if (!$this->hasFatalErrorHandler || !\in_array($code, self::$fatalErrors, \true)) {
            $level = isset($this->errorLevelMap[$code]) ? $this->errorLevelMap[$code] : LogLevel::CRITICAL;
            $this->logger->log($level, self::codeToString($code) . ': ' . $message, ['code' => $code, 'message' => $message, 'file' => $file, 'line' => $line, 'type' => 'errors']);
        }

        if ($this->previousErrorHandler === \true) {
            return \false;
        } elseif ($this->previousErrorHandler) {
            return \call_user_func($this->previousErrorHandler, $code, $message, $file, $line, $context);
        }

        return \null;
    }

    /**
     * @private
     */
    public function handleFatalError()
    {
        $this->reservedMemory = \null;

        $lastError = \error_get_last();
        if ($lastError && \in_array($lastError['type'], self::$fatalErrors, \true)) {
            $this->logger->log(
                $this->fatalLevel === \null ? LogLevel::ALERT : $this->fatalLevel,
                'Fatal Error (' . self::codeToString($lastError['type']) . '): ' . $lastError['message'],
                ['code' => $lastError['type'], 'message' => $lastError['message'], 'file' => $lastError['file'], 'line' => $lastError['line'], 'type' => 'fatals']
            );
        }
    }

    private static function codeToString($code)
    {
        switch ($code) {
            case \E_ERROR:
                return 'E_ERROR';
            case \E_WARNING:
                return 'E_WARNING';
            case \E_PARSE:
                return 'E_PARSE';
            case \E_NOTICE:
                return 'E_NOTICE';
            case \E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case \E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case \E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case \E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case \E_USER_ERROR:
                return 'E_USER_ERROR';
            case \E_USER_WARNING:
                return 'E_USER_WARNING';
            case \E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case 2048: // E_STRICT
                return 'E_STRICT';
            case \E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case \E_DEPRECATED:
                return 'E_DEPRECATED';
            case \E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
        }

        return 'Unknown PHP error';
    }
}