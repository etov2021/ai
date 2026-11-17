<?php

declare(strict_types=1);

namespace Ai\Calendar;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\HttpException;
use Ai\Exception\ToolException;
use Ai\Gmail\GmailOAuth;
use Ai\Google\GoogleToken;

/** Google Calendar (основния календар на свързания акаунт): четене на събития и създаване. Съобщенията са безопасни за модела. */
final class CalendarClient
{
    private const API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    private const DATE = '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})?)?$/';

    private \DateTimeZone $zone;

    /** @param \Closure(): list<string> $scopes разрешените обхвати на акаунта; чете се лениво (изисква база и ключ) */
    public function __construct(
        private HttpClientInterface $http,
        private GoogleToken $token,
        string $timezone,
        private \Closure $scopes,
    ) {
        $this->zone = new \DateTimeZone($timezone);
    }

    public function timezone(): string
    {
        return $this->zone->getName();
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->zone);
    }

    /** Приема "2026-11-20", "2026-11-20 14:30" и ISO 8601; без отместване важи часовата зона на календара. */
    public function parse(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match(self::DATE, $value) !== 1) {
            throw new ToolException('Невалидна дата. Формат: ГГГГ-ММ-ДД или ГГГГ-ММ-ДД ЧЧ:ММ.');
        }
        try {
            return (new \DateTimeImmutable($value, $this->zone))->setTimezone($this->zone);
        } catch (\Exception) {
            throw new ToolException('Невалидна дата.');
        }
    }

    /** @return list<array<string, mixed>> start/end/allDay/summary/location/description за инструментите и id/link/meet/organizer/attendees/descriptionFull/tentative за страницата */
    public function events(\DateTimeImmutable $from, \DateTimeImmutable $to, string $query, int $limit): array
    {
        $params = [
            'timeMin' => $from->format(DATE_RFC3339),
            'timeMax' => $to->format(DATE_RFC3339),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => max(1, min($limit, 250)),
            'timeZone' => $this->zone->getName(),
        ];
        if ($query !== '') {
            $params['q'] = $query;
        }

        $data = $this->request('GET', self::API . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        $events = [];
        foreach ((array) ($data['items'] ?? []) as $item) {
            if (!is_array($item) || ($item['status'] ?? '') === 'cancelled') {
                continue;
            }
            $allDay = isset($item['start']['date']);
            $events[] = [
                'start' => $this->format($item['start'] ?? [], false),
                'end' => $this->format($item['end'] ?? [], $allDay),
                'allDay' => $allDay,
                'summary' => self::clean((string) ($item['summary'] ?? '(без заглавие)'), 200),
                'location' => self::clean((string) ($item['location'] ?? ''), 200),
                'description' => self::clean((string) ($item['description'] ?? ''), 300),
                'id' => (string) ($item['id'] ?? ''),
                'link' => (string) ($item['htmlLink'] ?? ''),
                'meet' => self::meetLink($item),
                'organizer' => self::clean((string) ($item['organizer']['displayName'] ?? $item['organizer']['email'] ?? ''), 120),
                'attendees' => self::attendees((array) ($item['attendees'] ?? [])),
                'descriptionFull' => self::text((string) ($item['description'] ?? ''), 1500),
                'tentative' => ($item['status'] ?? '') === 'tentative',
            ];
        }

        return $events;
    }

    /** @return array{id: string, link: string} */
    public function createEvent(string $summary, ?string $description, ?string $location, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $body = [
            'summary' => $summary,
            'start' => ['dateTime' => $start->format(DATE_RFC3339), 'timeZone' => $this->zone->getName()],
            'end' => ['dateTime' => $end->format(DATE_RFC3339), 'timeZone' => $this->zone->getName()],
        ];
        if ($description !== null && $description !== '') {
            $body['description'] = $description;
        }
        if ($location !== null && $location !== '') {
            $body['location'] = $location;
        }

        $data = $this->request('POST', self::API, $body);

        return ['id' => (string) ($data['id'] ?? ''), 'link' => (string) ($data['htmlLink'] ?? '')];
    }

    /**
     * @param ?array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $scopes = ($this->scopes)();
        if ($scopes !== [] && !in_array(GmailOAuth::CALENDAR_SCOPE, $scopes, true)) {
            throw new ToolException('Календарът не е разрешен за акаунта. Администраторът трябва да го свърже наново и да разреши календара.');
        }

        $headers = ['Authorization' => 'Bearer ' . $this->token->get(), 'Accept' => 'application/json'];

        try {
            $response = $method === 'POST'
                ? $this->http->post($url, $headers + ['Content-Type' => 'application/json'], (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 20)
                : $this->http->get($url, $headers, 20);
        } catch (HttpException) {
            throw new ToolException('Google Calendar не отговаря.');
        }

        if ($response->status === 401 || $response->status === 403) {
            throw new ToolException('Няма достъп до календара. Провери дали Google Calendar API е включен и дали акаунтът е свързан наново с разрешен календар.');
        }
        $data = json_decode($response->body, true);
        if (!in_array($response->status, [200, 201], true) || !is_array($data)) {
            throw new ToolException(sprintf('Google Calendar върна грешка (HTTP %d).', $response->status));
        }

        return $data;
    }

    /** @param array<string, mixed> $point */
    private function format(array $point, bool $exclusiveEnd): string
    {
        if (isset($point['date'])) {
            $date = new \DateTimeImmutable((string) $point['date'], $this->zone);

            // Краят на целодневно събитие в Google е изключващ: следващият ден.
            return ($exclusiveEnd ? $date->modify('-1 day') : $date)->format('Y-m-d');
        }
        try {
            return (new \DateTimeImmutable((string) ($point['dateTime'] ?? 'now')))->setTimezone($this->zone)->format('Y-m-d H:i');
        } catch (\Exception) {
            return '';
        }
    }

    private static function clean(string $text, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\p{C}\s]+/u', ' ', $text) ?? ''), 0, $max);
    }

    /** Описанията в Google често са HTML: тагове се махат, а новите редове се пазят. */
    private static function text(string $html, int $max): string
    {
        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|li|tr)>#i'], "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? '';

        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? ''), 0, $max);
    }

    /** @param array<string, mixed> $item */
    private static function meetLink(array $item): string
    {
        $link = (string) ($item['hangoutLink'] ?? '');
        foreach ((array) ($item['conferenceData']['entryPoints'] ?? []) as $entry) {
            if (($entry['entryPointType'] ?? '') === 'video' && is_string($entry['uri'] ?? null)) {
                $link = $entry['uri'];
                break;
            }
        }

        // Само http(s): връзката се показва като адрес за отваряне.
        return preg_match('#^https://#i', $link) === 1 ? $link : '';
    }

    /**
     * @param array<int, mixed> $attendees
     * @return list<array{name: string, status: string}>
     */
    private static function attendees(array $attendees): array
    {
        $out = [];
        foreach (array_slice($attendees, 0, 20) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $out[] = ['name' => self::clean((string) ($a['displayName'] ?? $a['email'] ?? ''), 120), 'status' => (string) ($a['responseStatus'] ?? '')];
        }

        return $out;
    }
}
