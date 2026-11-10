<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Exception\AiException;
use Ai\Message\ChatRequest;
use Ai\Message\ChatResponse;

interface ProviderInterface
{
    /** Уникален ключ на доставчика, напр. "openai". */
    public function name(): string;

    /** @throws AiException */
    public function chat(ChatRequest $request): ChatResponse;
}
