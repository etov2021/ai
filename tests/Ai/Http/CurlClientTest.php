<?php

declare(strict_types=1);

namespace Ai\Tests\Http;

use Ai\Exception\HttpException;
use Ai\Http\CurlClient;
use Ai\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class CurlClientTest extends TestCase
{
    /** @param list<HttpResponse|HttpException> $script */
    private function client(array $script, array &$sleeps): CurlClient
    {
        return new class ($script, $sleeps) extends CurlClient {
            public int $calls = 0;

            public function __construct(private array $script, array &$sleeps)
            {
                parent::__construct(2, 10, 100, static function (int $ms) use (&$sleeps): void {
                    $sleeps[] = $ms;
                });
            }

            protected function perform(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
            {
                $step = $this->script[$this->calls++];
                if ($step instanceof HttpException) {
                    throw $step;
                }
                return $step;
            }
        };
    }

    public function testRetriesOn429WithBackoffThenSucceeds(): void
    {
        $sleeps = [];
        $client = $this->client([new HttpResponse(429, ''), new HttpResponse(503, ''), new HttpResponse(200, '{}')], $sleeps);

        $this->assertSame(200, $client->post('https://x', [], '{}', 5)->status);
        $this->assertSame([100, 200], $sleeps);
    }

    public function testRetryAfterHeaderIsHonouredAndCapped(): void
    {
        $sleeps = [];
        $client = $this->client([new HttpResponse(429, '', ['retry-after' => '3']), new HttpResponse(429, '', ['retry-after' => '999']), new HttpResponse(200, '')], $sleeps);

        $client->post('https://x', [], '{}', 5);

        $this->assertSame([3000, 10000], $sleeps);
    }

    public function testReturnsLastErrorResponseAfterMaxRetries(): void
    {
        $sleeps = [];
        $client = $this->client([new HttpResponse(500, 'a'), new HttpResponse(500, 'b'), new HttpResponse(500, 'c')], $sleeps);

        $this->assertSame('c', $client->post('https://x', [], '{}', 5)->body);
        $this->assertCount(2, $sleeps);
    }

    public function testClientErrorsAreNotRetried(): void
    {
        $sleeps = [];
        $client = $this->client([new HttpResponse(400, 'bad')], $sleeps);

        $this->assertSame(400, $client->post('https://x', [], '{}', 5)->status);
        $this->assertSame([], $sleeps);
    }

    public function testNonRetryableTransportErrorIsThrownImmediately(): void
    {
        $sleeps = [];
        $client = $this->client([new HttpException('timeout', 28, false)], $sleeps);

        $this->expectException(HttpException::class);
        try {
            $client->post('https://x', [], '{}', 5);
        } finally {
            $this->assertSame([], $sleeps);
        }
    }
}
