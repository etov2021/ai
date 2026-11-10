<?php

declare(strict_types=1);

namespace Ai\Tests;

use Ai\Contract\HttpClientInterface;
use Ai\Http\HttpResponse;

class FakeHttpClient implements HttpClientInterface
{
    public ?string $url = null;
    public ?string $method = null;
    /** @var array<string, string> */
    public array $headers = [];
    public ?array $payload = null;

    /** @param array<string, mixed>|string $body */
    public function __construct(private array|string $body, private int $status = 200)
    {
    }

    public function post(string $url, array $headers, string $body, int $timeout): HttpResponse
    {
        $this->method = 'POST';
        $this->url = $url;
        $this->headers = $headers;
        $this->payload = json_decode($body, true);

        return new HttpResponse($this->status, is_array($this->body) ? json_encode($this->body) : $this->body);
    }

    public function get(string $url, array $headers, int $timeout): HttpResponse
    {
        $this->method = 'GET';
        $this->url = $url;
        $this->headers = $headers;

        return new HttpResponse($this->status, is_array($this->body) ? json_encode($this->body) : $this->body);
    }
}
