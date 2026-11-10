<?php

declare(strict_types=1);

namespace Ai\Exception;

/** Грешка, върната от LLM доставчика (HTTP статус >= 400 или неочакван отговор). */
class ProviderException extends AiException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    public function isRetryable(): bool
    {
        return $this->status !== null && ($this->status === 408 || $this->status === 429 || $this->status >= 500);
    }
}
