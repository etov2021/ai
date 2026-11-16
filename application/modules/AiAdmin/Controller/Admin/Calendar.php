<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class Calendar extends BaseController
{
    public function indexAction()
    {
        $client = $this->ai()->calendarClient();
        $from = $client->now();

        $events = [];
        try {
            $events = $client->events($from, $from->modify('+14 days'), '', 30);
            $eventsError = null;
        } catch (AiException $e) {
            $eventsError = $e->getMessage();
        } catch (\Throwable) {
            $eventsError = 'Календарът не може да се прочете (проверете миграциите и ключа).';
        }

        $pending = $recent = [];
        try {
            $pending = $this->ai()->calendarProposals()->pending();
            $recent = $this->ai()->calendarProposals()->recent(20);
        } catch (\Throwable) {
            flash()->setMessage('Предложенията не могат да се прочетат: изпълнете миграция 011.', 'warning');
        }

        return [
            'form' => new Form($this->formPath('calendar')),
            'events' => $events,
            'eventsError' => $eventsError,
            'timezone' => $client->timezone(),
            'pending' => $pending,
            'recent' => $recent,
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
