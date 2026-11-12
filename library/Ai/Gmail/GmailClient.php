<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Exception\ToolException;

/** Само четене от Gmail API. Съобщенията на изключенията са безопасни за връщане към модела. */
final class GmailClient
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me/messages';
    private const MAX_BODY_CHARS = 6000;

    private ?string $accessToken = null;
    private int $expiresAt = 0;

    /** @param \Closure(): ?string $refreshToken чете се лениво, така че липсващ AI_MASTER_KEY да не чупи регистрацията */
    public function __construct(
        private HttpClientInterface $http,
        private ?string $clientId,
        #[\SensitiveParameter] private ?string $clientSecret,
        private \Closure $refreshToken,
    ) {
    }

    /** @return list<array{id: string, from: string, subject: string, date: string, snippet: string}> */
    public function search(string $query, int $limit): array
    {
        $list = $this->get(self::API . '?' . http_build_query(['q' => $query, 'maxResults' => $limit], '', '&', PHP_QUERY_RFC3986));

        $messages = [];
        foreach (array_slice((array) ($list['messages'] ?? []), 0, $limit) as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $meta = $this->get(self::API . '/' . rawurlencode($id) . '?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Date');
            $headers = self::headers($meta);
            $messages[] = [
                'id' => $id,
                'from' => $headers['from'] ?? '',
                'subject' => $headers['subject'] ?? '',
                'date' => $headers['date'] ?? '',
                'snippet' => self::clean((string) ($meta['snippet'] ?? ''), 200),
            ];
        }

        return $messages;
    }

    /** @return array{from: string, to: string, subject: string, date: string, body: string, truncated: bool} */
    public function read(string $id): array
    {
        $message = $this->get(self::API . '/' . rawurlencode($id) . '?format=full');
        $headers = self::headers($message, ['From', 'To', 'Subject', 'Date']);
        $body = self::bodyText((array) ($message['payload'] ?? []));

        return [
            'from' => $headers['from'] ?? '',
            'to' => $headers['to'] ?? '',
            'subject' => $headers['subject'] ?? '',
            'date' => $headers['date'] ?? '',
            'body' => mb_substr($body, 0, self::MAX_BODY_CHARS),
            'truncated' => mb_strlen($body) > self::MAX_BODY_CHARS,
        ];
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        try {
            $response = $this->http->get($url, ['Authorization' => 'Bearer ' . $this->token(), 'Accept' => 'application/json'], 15);
        } catch (HttpException) {
            throw new ToolException('Gmail не отговаря.');
        }

        if ($response->status === 401 || $response->status === 403) {
            throw new ToolException('Няма достъп до Gmail. Акаунтът трябва да се свърже наново от администратора.');
        }
        $data = json_decode($response->body, true);
        if ($response->status === 404) {
            throw new ToolException('Писмото не е намерено.');
        }
        if ($response->status !== 200 || !is_array($data)) {
            throw new ToolException('Gmail върна грешка.');
        }

        return $data;
    }

    private function token(): string
    {
        if ($this->accessToken !== null && time() < $this->expiresAt - 30) {
            return $this->accessToken;
        }
        if ($this->clientId === null || $this->clientId === '' || $this->clientSecret === null || $this->clientSecret === '') {
            throw new ToolException('Gmail не е настроен от администратора.');
        }

        try {
            $refresh = ($this->refreshToken)();
        } catch (AiException) {
            throw new ToolException('Gmail не е свързан.');
        }
        if ($refresh === null) {
            throw new ToolException('Gmail не е свързан.');
        }

        try {
            $response = $this->http->post(
                GmailOAuth::TOKEN_URL,
                ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
                http_build_query(['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'refresh_token' => $refresh, 'grant_type' => 'refresh_token']),
                15,
            );
        } catch (HttpException) {
            throw new ToolException('Gmail не отговаря.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data) || !is_string($data['access_token'] ?? null)) {
            throw new ToolException('Няма достъп до Gmail. Акаунтът трябва да се свърже наново от администратора.');
        }

        $this->expiresAt = time() + (int) ($data['expires_in'] ?? 300);

        return $this->accessToken = $data['access_token'];
    }

    /**
     * @param array<string, mixed> $message
     * @param list<string> $only
     * @return array<string, string> имената са в малки букви
     */
    private static function headers(array $message, array $only = ['From', 'Subject', 'Date']): array
    {
        $wanted = array_map('strtolower', $only);
        $result = [];
        foreach ((array) ($message['payload']['headers'] ?? []) as $header) {
            $name = strtolower((string) ($header['name'] ?? ''));
            if (in_array($name, $wanted, true)) {
                $result[$name] = self::clean((string) ($header['value'] ?? ''), 200);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private static function bodyText(array $payload): string
    {
        $plain = self::findPart($payload, 'text/plain');
        if ($plain !== null) {
            return self::normalize(self::decodePart($plain));
        }
        $html = self::findPart($payload, 'text/html');
        if ($html !== null) {
            $text = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', self::decodePart($html)) ?? '';
            $text = preg_replace('#<(br|/p|/div|/tr|/li)\b[^>]*>#i', "\n", $text) ?? '';

            return self::normalize(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    /** @param array<string, mixed> $part @return ?array<string, mixed> */
    private static function findPart(array $part, string $mime): ?array
    {
        if (strtolower((string) ($part['mimeType'] ?? '')) === $mime && isset($part['body']['data'])) {
            return $part;
        }
        foreach ((array) ($part['parts'] ?? []) as $child) {
            if (is_array($child) && ($found = self::findPart($child, $mime)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $part */
    private static function decodePart(array $part): string
    {
        $raw = base64_decode(strtr((string) $part['body']['data'], '-_', '+/'), false);
        if ($raw === false) {
            return '';
        }

        $charset = 'UTF-8';
        foreach ((array) ($part['headers'] ?? []) as $header) {
            if (strtolower((string) ($header['name'] ?? '')) === 'content-type' && preg_match('/charset="?([A-Za-z0-9_-]+)/i', (string) $header['value'], $m) === 1) {
                $charset = $m[1];
            }
        }
        if (strcasecmp($charset, 'UTF-8') !== 0 && in_array(strtoupper($charset), array_map('strtoupper', mb_list_encodings()), true)) {
            $raw = mb_convert_encoding($raw, 'UTF-8', $charset);
        }

        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? '';

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? '') ?? '');
    }

    private static function clean(string $text, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\p{C}\s]+/u', ' ', $text) ?? ''), 0, $max);
    }
}
