<?php

declare(strict_types=1);

namespace Ai\Exception;

final class RateLimitException extends LimitException
{
    public function __construct(public readonly int $retryAfterSeconds, string $reason)
    {
        parent::__construct(sprintf('Твърде много заявки. Опитайте след %d сек.', $retryAfterSeconds), $reason);
    }
}
