<?php

declare(strict_types=1);

namespace Ai\Exception;

/**
 * Заявка, отхвърлена от ограничение. Съобщението е безопасно за потребителя;
 * $reason съдържа подробности (кое правило, какви стойности) само за лога.
 */
abstract class LimitException extends AiException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
