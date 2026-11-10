<?php

declare(strict_types=1);

namespace Ai\Exception;

/** Транспортна грешка: връзка, DNS, TLS, таймаут. */
class HttpException extends AiException
{
    public function __construct(string $message, public readonly int $curlErrno = 0, public readonly bool $retryable = false)
    {
        parent::__construct($message, $curlErrno);
    }
}
