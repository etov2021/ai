<?php

namespace Micro\Exception;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel as LogLevel;

class Exception extends \Exception
{
    /**
     * @var Logger
     */
    private static $logger;

    public function __construct($message = \null, $code = 500, $previous = \null)
    {
        parent::__construct($message, $code, $previous);

        if (self::$logger !== \null) {

            $level = LogLevel::ALERT;

            switch ($code) {
                case 600:
                case 601:
                case 602:
                case 603:
                    $level = LogLevel::WARNING;
                    break;
            }

            self::$logger->log(LogLevel::ALERT, $message, array(
                'code'    => $this->getCode(),
                'message' => $this->getMessage(),
                'file'    => $this->getFile(),
                'line'    => $this->getLine(),
                'type'    => 'exceptions'
            ));
        }
    }

    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
    }
}
