<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Message\Role;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Chat extends BaseController
{
    private const STATUS_TEXT = [
        'truncated' => 'Отговорът е съкратен от лимита на изходните токени.',
        'blocked' => 'Отговорът е блокиран от филтъра на доставчика.',
        'max_steps' => 'Агентът е спрян: достигнат е лимитът на стъпките.',
        'timeout' => 'Агентът е спрян: изтекло е времето за изпълнение.',
    ];

    public function indexAction()
    {
        $agents = $this->safely(fn () => array_values(array_filter($this->ai()->agents()->all(), fn ($a) => $a->runnable)));
        $conversationId = (int) $this->request->getParam('conversation', 0);
        $userId = $this->userId();

        $conversation = null;
        $agent = null;
        $messages = [];

        if ($conversationId > 0) {
            $conversation = $this->ai()->conversations()->find($conversationId);
            // Чужд и несъществуващ разговор са неразличими.
            if ($conversation === null || $conversation->userId !== $userId) {
                throw new CoreException('Разговорът не е намерен', 404);
            }
            foreach ($agents as $candidate) {
                if ($candidate->id === $conversation->agentId) {
                    $agent = $candidate;
                }
            }
            $messages = array_values(array_filter(
                $this->ai()->conversations()->history($conversation->id, 200),
                static fn ($m) => in_array($m->role, [Role::User, Role::Assistant], true) && $m->content !== null && $m->content !== '',
            ));
        }

        $form = new Form($this->formPath('chat'));
        $options = [];
        foreach ($agents as $a) {
            $options[$a->code] = $a->name;
        }
        $form->getElement('agent')->setMultiOptions($options);
        if ($agent) {
            // Агентът на вече започнат разговор не се сменя.
            $form->removeElement('agent');
        } else {
            $form->populate(['agent' => (string) $this->request->getParam('agent', '')]);
        }

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                $agentCode = $agent ? $agent->code : (string) $post['agent'];

                try {
                    $reply = $this->ai()->chat()->ask($agentCode, (string) $post['message'], $conversation?->id, $userId);

                    $this->report($reply->result);

                    return $this->redirect($this->url('chat', 'index', null, ['conversation' => $reply->conversationId]));
                } catch (AiException $e) {
                    flash()->setMessage(Redactor::redact($e->getMessage()), 'danger');
                }
            }
        }

        return [
            'form' => $form,
            'agent' => $agent,
            'messages' => $messages,
            'newUrl' => $this->url('chat'),
            'homeUrl' => $this->url('index'),
        ];
    }

    private function report($result): void
    {
        if (isset(self::STATUS_TEXT[$result->status->value])) {
            flash()->setMessage(self::STATUS_TEXT[$result->status->value], 'warning');
        }

        flash()->setMessage(sprintf(
            'Токени: %d вход, %d изход. Разход: %.4f EUR. Време: %d мс.',
            $result->usage->inputTokens, $result->usage->outputTokens, $result->cost, $result->durationMs,
        ), 'info');
    }
}
