<?php

declare(strict_types=1);

namespace Ai\Http;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;

class CurlClient implements HttpClientInterface
{
    // Само грешки преди изпращане на заявката: повторен опит след таймаут на отговора би таксувал два пъти.
    private const RETRYABLE_ERRNO = [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR];
    private const RETRYABLE_STATUS = [429, 500, 502, 503, 504, 529];
    private const MAX_RETRY_AFTER = 10;

    /** @var callable(int): void */
    private $sleeper;

    /** @param ?callable(int): void $sleeper пауза в милисекунди; подменя се в тестове */
    public function __construct(
        private int $maxRetries = 2,
        private int $connectTimeout = 10,
        private int $baseDelayMs = 500,
        ?callable $sleeper = null,
        private int $maxResponseBytes = 8_000_000,
    ) {
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function post(string $url, array $headers, string $body, int $timeout): HttpResponse
    {
        return $this->send('POST', $url, $headers, $body, $timeout);
    }

    public function get(string $url, array $headers, int $timeout): HttpResponse
    {
        return $this->send('GET', $url, $headers, null, $timeout);
    }

    private function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->perform($method, $url, $headers, $body, $timeout);
            } catch (HttpException $e) {
                if (!$e->retryable || $attempt >= $this->maxRetries) {
                    throw $e;
                }
                ($this->sleeper)($this->delayMs($attempt, null));
                continue;
            }

            if (!in_array($response->status, self::RETRYABLE_STATUS, true) || $attempt >= $this->maxRetries) {
                return $response;
            }

            ($this->sleeper)($this->delayMs($attempt, $response->headers['retry-after'] ?? null));
        }
    }

    protected function perform(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        $received = '';
        $maxBytes = $this->maxResponseBytes;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $lines,
            // Надвишаването на лимита прекъсва връзката (грешка 23), вместо да пълни паметта.
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$received, $maxBytes): int {
                $received .= $chunk;
                return strlen($received) > $maxBytes ? 0 : strlen($chunk);
            },
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            // Без пренасочвания: заглавието с API ключа не бива да стига до друг хост.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        if ($method === 'POST') {
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => (string) $body]);
        }

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new HttpException('HTTP грешка: ' . $error, $errno, in_array($errno, self::RETRYABLE_ERRNO, true));
        }

        return new HttpResponse($status, $received, $responseHeaders);
    }

    private function delayMs(int $attempt, ?string $retryAfter): int
    {
        if ($retryAfter !== null && ctype_digit($retryAfter)) {
            return min((int) $retryAfter, self::MAX_RETRY_AFTER) * 1000;
        }

        return $this->baseDelayMs * (2 ** $attempt);
    }
}
