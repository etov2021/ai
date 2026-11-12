<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class Mail extends BaseController
{
    public function indexAction()
    {
        return [
            'rows' => $this->safely(fn () => $this->ai()->mailMessages()->latest(100)),
            'form' => new Form($this->formPath('mail-read')),
            'readUrl' => $this->url('mail', 'read'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function readAction()
    {
        $form = new Form($this->formPath('mail-read'));

        if ($this->request->isPost() && $form->isValid($this->post())) {
            try {
                $this->ai()->mailMessages()->markAllSeen();

                return $this->redirect($this->url('mail'), 'Известията са маркирани като прочетени.');
            } catch (AiException $e) {
                return $this->redirect($this->url('mail'), $e->getMessage(), 'danger');
            }
        }

        return $this->redirect($this->url('mail'));
    }
}
