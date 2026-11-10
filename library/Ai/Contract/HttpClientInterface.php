<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Exception\HttpException;
use Ai\Http\HttpResponse;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @throws HttpException
     */
    public function post(string $url, array $headers, string $body, int $timeout): HttpResponse;

    /**
     * @param array<string, string> $headers
     * @throws HttpException
     */
    public function get(string $url, array $headers, int $timeout): HttpResponse;
}
