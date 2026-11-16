<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ToolException;
use Ai\Google\GoogleToken;

/** Само четене от Gmail API. Съобщенията на изключенията са безопасни за връщане към модела. */
final class GmailClient
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me/messages';
    private const MAX_BODY_CHARS = 6000;

    /** @param \Closure(): list<string> $scopes разрешените обхвати на акаунта; чете се лениво */
    public function __construct(private HttpClientInterface $http, private GoogleToken $token, private \Closure $scopes)
    {
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
        // Само за акаунт с познати обхвати: празен списък означава версия без записани обхвати.
        $scopes = ($this->scopes)();
        if ($scopes !== [] && !in_array(GmailOAuth::SCOPE, $scopes, true)) {
            throw new ToolException('Достъпът до пощата не е разрешен. Акаунтът трябва да се свърже наново и на екрана на Google да се отбеляжи пощата.');
        }

        try {
            $response = $this->http->get($url, ['Authorization' => 'Bearer ' . $this->token->get(), 'Accept' => 'application/json'], 15);
        } catch (HttpException) {
            throw new ToolException('Gmail не отговаря.');
        }

        if ($response->status === 401 || $response->status === 403) {
            throw new ToolException(self::accessError($response->body, $response->status));
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

    /** Съобщението сочи причината (изключен API или липсващо право); връща се само наш текст, не отговорът на Google. */
    private static function accessError(string $body, int $status): string
    {
        $error = json_decode($body, true)['error'] ?? [];
        $reason = strtolower((string) ($error['errors'][0]['reason'] ?? $error['details'][0]['reason'] ?? ''));
        $text = strtolower((string) ($error['message'] ?? ''));

        if (str_contains($reason, 'accessnotconfigured') || str_contains($reason, 'service_disabled') || str_contains($text, 'has not been used') || str_contains($text, 'is disabled')) {
            return 'Gmail API не е включен в Google Cloud проекта. Администраторът трябва да го включи.';
        }
        if (str_contains($reason, 'insufficient') || str_contains($reason, 'scope') || str_contains($text, 'insufficient')) {
            return 'Липсва разрешение за поща. Акаунтът трябва да се свърже наново и да се отбеляжи пощата.';
        }

        return sprintf('Няма достъп до Gmail (HTTP %d). Акаунтът трябва да се свърже наново от администратора.', $status);
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
