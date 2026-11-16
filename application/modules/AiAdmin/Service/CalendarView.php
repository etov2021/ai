<?php

namespace AiAdmin\Service;

/** Подготвя данните за визуалния календар: периоди, подредба по дни, колони при припокриване и текстове за показване. */
final class CalendarView
{
    public const MONTHS = ['януари', 'февруари', 'март', 'април', 'май', 'юни', 'юли', 'август', 'септември', 'октомври', 'ноември', 'декември'];
    public const DAYS = ['понеделник', 'вторник', 'сряда', 'четвъртък', 'петък', 'събота', 'неделя'];
    public const DAYS_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'];
    public const VIEWS = ['month' => 'Месец', 'week' => 'Седмица', 'agenda' => 'Списък'];

    private const RESPONSE = ['accepted' => 'приел', 'declined' => 'отказал', 'tentative' => 'може би', 'needsAction' => 'без отговор'];
    // Пикселите на минута във времевата мрежа: 48px на час.
    public const PX_PER_MINUTE = 0.8;

    /**
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable, title: string, prev: string, next: string, view: string}
     *         to е изключващ; prev/next са начални дати на съседните периоди (ГГГГ-ММ-ДД)
     */
    public static function range(string $view, \DateTimeImmutable $date): array
    {
        $view = isset(self::VIEWS[$view]) ? $view : 'month';
        $day = $date->setTime(0, 0);

        if ($view === 'week') {
            $from = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');
            $to = $from->modify('+7 days');
            $last = $to->modify('-1 day');
            $title = $from->format('j') . ' ' . ($from->format('n') === $last->format('n') ? '' : self::MONTHS[(int) $from->format('n') - 1] . ' ') . '- ' . $last->format('j') . ' ' . self::MONTHS[(int) $last->format('n') - 1] . ' ' . $last->format('Y');

            return ['from' => $from, 'to' => $to, 'title' => $title, 'prev' => $from->modify('-7 days')->format('Y-m-d'), 'next' => $from->modify('+7 days')->format('Y-m-d'), 'view' => $view];
        }

        if ($view === 'agenda') {
            return ['from' => $day, 'to' => $day->modify('+30 days'), 'title' => 'Следващите 30 дни от ' . $day->format('j') . ' ' . self::MONTHS[(int) $day->format('n') - 1], 'prev' => $day->modify('-30 days')->format('Y-m-d'), 'next' => $day->modify('+30 days')->format('Y-m-d'), 'view' => $view];
        }

        $first = $day->modify('first day of this month');
        $from = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');

        $monthName = self::MONTHS[(int) $first->format('n') - 1];

        return [
            'from' => $from,
            'to' => $from->modify('+42 days'),
            'title' => mb_strtoupper(mb_substr($monthName, 0, 1)) . mb_substr($monthName, 1) . ' ' . $first->format('Y'),
            'prev' => $first->modify('-1 month')->format('Y-m-d'),
            'next' => $first->modify('+1 month')->format('Y-m-d'),
            'view' => 'month',
        ];
    }

    /**
     * Обединява събитията от Google и чакащите предложения в един списък.
     *
     * @param list<array<string, mixed>> $events резултат от CalendarClient::events()
     * @param list<array<string, mixed>> $proposals редове от AiCalendarProposals със статус pending
     * @return list<array<string, mixed>>
     */
    public static function items(array $events, array $proposals, \DateTimeZone $zone): array
    {
        $items = [];

        foreach ($events as $i => $e) {
            try {
                $allDay = (bool) $e['allDay'];
                $start = new \DateTimeImmutable((string) $e['start'], $zone);
                // Целодневният край е включващ дата; във времевата мрежа краят е изключващ (следващия ден).
                $end = $allDay ? (new \DateTimeImmutable((string) $e['end'], $zone))->modify('+1 day') : new \DateTimeImmutable((string) $e['end'], $zone);
            } catch (\Exception) {
                continue;
            }
            if ($end <= $start) {
                $end = $start->modify('+30 minutes');
            }
            $items[] = [
                'key' => 'e' . $i,
                'type' => 'event',
                'title' => (string) $e['summary'],
                'start' => $start,
                'end' => $end,
                'allDay' => $allDay,
                'location' => (string) $e['location'],
                'description' => (string) ($e['descriptionFull'] ?? $e['description']),
                'link' => (string) ($e['link'] ?? ''),
                'meet' => (string) ($e['meet'] ?? ''),
                'organizer' => (string) ($e['organizer'] ?? ''),
                'attendees' => (array) ($e['attendees'] ?? []),
                'tentative' => (bool) ($e['tentative'] ?? false),
                'color' => self::color((string) $e['summary']),
                'proposalId' => 0,
            ];
        }

        foreach ($proposals as $p) {
            try {
                $tz = new \DateTimeZone((string) $p['timezone']);
                $start = new \DateTimeImmutable((string) $p['startAt'], $tz);
                $end = new \DateTimeImmutable((string) $p['endAt'], $tz);
            } catch (\Exception) {
                continue;
            }
            $items[] = [
                'key' => 'p' . (int) $p['id'],
                'type' => 'proposal',
                'title' => (string) $p['summary'],
                'start' => $start->setTimezone($zone),
                'end' => $end->setTimezone($zone),
                'allDay' => false,
                'location' => (string) ($p['location'] ?? ''),
                'description' => (string) ($p['description'] ?? ''),
                'link' => '',
                'meet' => '',
                'organizer' => '',
                'attendees' => [],
                'tentative' => false,
                'color' => 6,
                'proposalId' => (int) $p['id'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => [$a['start'], $a['title']] <=> [$b['start'], $b['title']]);

        return $items;
    }

    /**
     * Разпределя елементите по дни; събитие през няколко дни влиза във всеки от тях.
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, list<array<string, mixed>>> ключ ГГГГ-ММ-ДД
     */
    public static function byDay(array $items, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        foreach ($items as $item) {
            $day = $item['start']->setTime(0, 0);
            $last = $item['end']->modify('-1 second')->setTime(0, 0);
            if ($last < $day) {
                $last = $day;
            }
            for (; $day <= $last; $day = $day->modify('+1 day')) {
                if ($day < $from || $day >= $to) {
                    continue;
                }
                $dayEnd = $day->modify('+1 day');
                $days[$day->format('Y-m-d')][] = $item + [
                    'segStart' => max($item['start'], $day),
                    'segEnd' => min($item['end'], $dayEnd),
                    'first' => $item['start'] >= $day,
                    'last' => $item['end'] <= $dayEnd,
                ];
            }
        }

        return $days;
    }

    /**
     * Подрежда времевите елементи на един ден в колони, когато се припокриват.
     *
     * @param list<array<string, mixed>> $segments сегменти от byDay без целодневните
     * @return list<array<string, mixed>> със top, height (px), lane и lanes
     */
    public static function layoutDay(array $segments, \DateTimeImmutable $day): array
    {
        $out = [];
        foreach ($segments as $s) {
            $top = (int) round(((int) $s['segStart']->format('G') * 60 + (int) $s['segStart']->format('i')) * self::PX_PER_MINUTE);
            $minutes = max(30, (int) (($s['segEnd']->getTimestamp() - $s['segStart']->getTimestamp()) / 60));
            $out[] = $s + ['top' => $top, 'height' => (int) round(min($minutes, 1440) * self::PX_PER_MINUTE), 'lane' => 0, 'lanes' => 1];
        }
        usort($out, static fn (array $a, array $b): int => [$a['top'], -$a['height']] <=> [$b['top'], -$b['height']]);

        // Група = верига от припокриващи се елементи; всички в групата делят ширината поравно.
        $group = [];
        $groupEnd = -1;
        $flush = static function (array &$group) use (&$out): void {
            $lanes = $group === [] ? 1 : max(array_column($group, 'lane')) + 1;
            foreach ($group as $index => $_) {
                $out[$index]['lanes'] = $lanes;
            }
            $group = [];
        };

        $laneEnds = [];
        foreach ($out as $index => $s) {
            if ($s['top'] >= $groupEnd && $group !== []) {
                $flush($group);
                $laneEnds = [];
            }
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $s['top']) {
                $lane++;
            }
            $laneEnds[$lane] = $s['top'] + $s['height'];
            $out[$index]['lane'] = $lane;
            $group[$index] = ['lane' => $lane];
            $groupEnd = max($groupEnd, $s['top'] + $s['height']);
        }
        $flush($group);

        return $out;
    }

    /** Подробностите за прозореца при щракване; само текст, без HTML. */
    public static function details(array $item): array
    {
        $attendees = [];
        foreach ($item['attendees'] as $a) {
            $attendees[] = $a['name'] . ($a['status'] !== '' && isset(self::RESPONSE[$a['status']]) ? ' (' . self::RESPONSE[$a['status']] . ')' : '');
        }

        return [
            'type' => $item['type'],
            'title' => $item['title'],
            'when' => self::when($item),
            'location' => $item['location'],
            'description' => $item['description'],
            'organizer' => $item['organizer'],
            'attendees' => $attendees,
            'link' => $item['link'],
            'meet' => $item['meet'],
            'tentative' => $item['tentative'],
            'proposalId' => $item['proposalId'],
        ];
    }

    public static function when(array $item): string
    {
        $s = $item['start'];
        $date = static fn (\DateTimeImmutable $d): string => self::DAYS[(int) $d->format('N') - 1] . ', ' . $d->format('j') . ' ' . self::MONTHS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');

        if ($item['allDay']) {
            $last = $item['end']->modify('-1 day');

            return $s->format('Y-m-d') === $last->format('Y-m-d') ? $date($s) . ' (цял ден)' : $date($s) . ' - ' . $date($last) . ' (цял ден)';
        }

        return $s->format('Y-m-d') === $item['end']->format('Y-m-d')
            ? $date($s) . ', ' . $s->format('H:i') . ' - ' . $item['end']->format('H:i')
            : $date($s) . ' ' . $s->format('H:i') . ' - ' . $date($item['end']) . ' ' . $item['end']->format('H:i');
    }

    /** Цвят 0-5 по заглавие: еднакви събития са еднакво оцветени. */
    private static function color(string $title): int
    {
        return abs(crc32(mb_strtolower($title))) % 6;
    }
}
