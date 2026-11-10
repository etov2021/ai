<?php

declare(strict_types=1);

namespace Ai\Http;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers имената са в малки букви */
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }
}
