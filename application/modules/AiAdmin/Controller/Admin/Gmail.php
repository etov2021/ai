<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class Gmail extends BaseController
{
    public function indexAction()
    {
        $oauth = $this->ai()->gmailOAuth();
        $accounts = $this->safely(fn () => $this->ai()->gmailAccounts()->all());

        return [
            'configured' => $oauth->isConfigured(),
            'redirectUri' => $this->redirectUri(),
            'rows' => array_map(fn (array $a) => ['item' => $a, 'disconnect' => $this->url('gmail', 'disconnect', (string) $a['id'])], $accounts),
            'form' => new Form($this->formPath('gmail-disconnect')),
            'connectUrl' => $this->url('gmail', 'connect'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function connectAction()
    {
        $oauth = $this->ai()->gmailOAuth();
        if (!$oauth->isConfigured()) {
            return $this->redirect($this->url('gmail'), 'Липсват GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET в средата.', 'danger');
        }

        // Еднократен state срещу CSRF при връщането от Google.
        $state = bin2hex(random_bytes(16));
        session('AiGmail')->oauthState = $state;

        return $this->redirect($oauth->authorizationUrl($this->redirectUri(), $state));
    }

    public function callbackAction()
    {
        $session = session('AiGmail');
        $expected = (string) ($session->oauthState ?? '');
        unset($session->oauthState);

        $state = (string) $this->request->getParam('state', '');
        $code = (string) $this->request->getParam('code', '');

        if ($expected === '' || !hash_equals($expected, $state)) {
            return $this->redirect($this->url('gmail'), 'Невалидна или изтекла заявка за свързване. Опитайте отново.', 'danger');
        }
        if ($this->request->getParam('error', '') !== '' || $code === '') {
            return $this->redirect($this->url('gmail'), 'Свързването беше отказано.', 'danger');
        }

        try {
            $account = $this->ai()->gmailOAuth()->connect($code, $this->redirectUri());
            $this->ai()->gmailAccounts()->save($account['email'], $account['refreshToken'], \Ai\Gmail\GmailOAuth::SCOPE);
        } catch (AiException $e) {
            return $this->redirect($this->url('gmail'), $e->getMessage(), 'danger');
        }

        return $this->redirect($this->url('gmail'), 'Акаунтът ' . $account['email'] . ' е свързан.');
    }

    public function disconnectAction()
    {
        $form = new Form($this->formPath('gmail-disconnect'));

        if ($this->request->isPost() && $form->isValid($this->post())) {
            try {
                $this->ai()->gmailAccounts()->delete((int) $this->request->getParam('id', 0));

                return $this->redirect($this->url('gmail'), 'Акаунтът е премахнат.');
            } catch (AiException $e) {
                return $this->redirect($this->url('gmail'), $e->getMessage(), 'danger');
            }
        }

        return $this->redirect($this->url('gmail'));
    }

    private function redirectUri(): string
    {
        return server_url($this->url('gmail', 'callback'));
    }
}
