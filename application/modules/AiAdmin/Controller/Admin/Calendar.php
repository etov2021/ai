<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use AiAdmin\Service\CalendarView;
use Micro\Form\Form;

class Calendar extends BaseController
{
    public function indexAction()
    {
        $client = $this->ai()->calendarClient();
        $params = $this->request->getQueryParams();

        $zone = new \DateTimeZone($client->timezone());
        $anchor = $client->now();
        if (isset($params['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $params['date']) === 1) {
            try {
                $anchor = new \DateTimeImmutable((string) $params['date'], $zone);
            } catch (\Exception) {
            }
        }
        $range = CalendarView::range((string) ($params['view'] ?? 'month'), $anchor);

        $events = [];
        $eventsError = null;
        try {
            $events = $client->events($range['from'], $range['to'], '', 250);
        } catch (AiException $e) {
            $eventsError = $e->getMessage();
        } catch (\Throwable) {
            $eventsError = 'Календарът не може да се прочете (проверете миграциите и ключа).';
        }

        $pending = $recent = [];
        try {
            $pending = $this->ai()->calendarProposals()->pending();
            $recent = $this->ai()->calendarProposals()->recent(10);
        } catch (\Throwable) {
            flash()->setMessage('Предложенията не могат да се прочетат: изпълнете миграция 011.', 'warning');
        }

        $items = CalendarView::items($events, $pending, $zone);
        $days = CalendarView::byDay($items, $range['from'], $range['to']);

        $details = [];
        foreach ($items as $item) {
            $details[$item['key']] = CalendarView::details($item);
        }

        $nav = fn (string $view, \DateTimeImmutable|string $date): string => $this->url('calendar', 'index', null, ['view' => $view, 'date' => $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : $date]);

        return [
            'form' => new Form($this->formPath('calendar')),
            'view' => $range['view'],
            'title' => $range['title'],
            'from' => $range['from'],
            'to' => $range['to'],
            'anchor' => $anchor,
            'today' => $client->now(),
            'days' => $days,
            'details' => $details,
            'eventsError' => $eventsError,
            'timezone' => $client->timezone(),
            'pending' => $pending,
            'recent' => $recent,
            'items' => $items,
            'prevUrl' => $nav($range['view'], $range['prev']),
            'nextUrl' => $nav($range['view'], $range['next']),
            'todayUrl' => $nav($range['view'], $client->now()),
            'viewUrls' => array_map(fn (string $v): string => $nav($v, $anchor), array_combine(array_keys(CalendarView::VIEWS), array_keys(CalendarView::VIEWS))),
            'dayUrl' => fn (\DateTimeImmutable $d): string => $nav('week', $d),
            'approveUrl' => $this->url('calendar', 'approve'),
            'rejectUrl' => $this->url('calendar', 'reject'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function approveAction()
    {
        $id = $this->proposalId();
        if ($id === null) {
            return $this->redirect($this->url('calendar'));
        }

        $proposals = $this->ai()->calendarProposals();
        $proposal = $proposals->find($id);

        // Поемане преди заявката към Google: двойно натискане не създава две събития.
        if ($proposal === null || !$proposals->claim($id)) {
            return $this->redirect($this->url('calendar'), 'Предложението вече е обработено.', 'warning');
        }

        try {
            $zone = new \DateTimeZone((string) $proposal['timezone']);
            $event = $this->ai()->calendarClient()->createEvent(
                (string) $proposal['summary'],
                $proposal['description'] !== null ? (string) $proposal['description'] : null,
                $proposal['location'] !== null ? (string) $proposal['location'] : null,
                new \DateTimeImmutable((string) $proposal['startAt'], $zone),
                new \DateTimeImmutable((string) $proposal['endAt'], $zone),
            );
            $proposals->markCreated($id, $event['link'], $this->userId());

            return $this->redirect($this->url('calendar'), 'Събитието е създадено в календара.');
        } catch (\Throwable $e) {
            $message = $e instanceof AiException ? Redactor::redact($e->getMessage()) : 'Събитието не може да бъде създадено.';
            $proposals->markFailed($id, $message, $this->userId());

            return $this->redirect($this->url('calendar'), $message, 'danger');
        }
    }

    public function rejectAction()
    {
        $id = $this->proposalId();

        if ($id !== null) {
            try {
                if ($this->ai()->calendarProposals()->reject($id, $this->userId())) {
                    return $this->redirect($this->url('calendar'), 'Предложението е отхвърлено.');
                }
            } catch (\Throwable) {
            }
        }

        return $this->redirect($this->url('calendar'));
    }

    private function proposalId(): ?int
    {
        $post = $this->post();
        $form = new Form($this->formPath('calendar'));

        return $this->request->isPost() && $form->isValid($post) && ctype_digit((string) ($post['id'] ?? '')) ? (int) $post['id'] : null;
    }
}
