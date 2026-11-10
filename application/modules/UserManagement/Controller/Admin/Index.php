<?php

namespace UserManagement\Controller\Admin;

use Micro\Auth\Auth;
use Micro\Http\Response\RedirectResponse;
use UserManagement\Model\Users as ModelUsers;
use UserManagement\Model\Entity\User;
use Micro\Form\Form;
use Micro\Application\Utils;
use Micro\Application\Controller\Admin;
use Micro\Auth\Identity;
use UserManagement\Model\Table\Tokens;
use UserManagement\Model\Users;
use UserManagement\Model\Table\Users as TableUsers;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

class Index extends Admin
{
    protected $model = ModelUsers::class;

    public function indexAction()
    {
        return new RedirectResponse(route(null, ['controller' => 'users']));
    }

    /**
     * @NotAuthorize
     */
    public function loginAction()
    {
        $backTo = $this->request->getParam('backTo');

        $identity = identity();

        if ($identity) {
            return new RedirectResponse($backTo ?: route_reset('admin'));
        }

        if (config('auth.2fa')) {
            $auth = Auth::getInstance()->getIdentity();
            if ($auth && $auth['user']->getAuth2fa() && isset($auth['step']) && $auth['step'] < 2) {
                return new RedirectResponse(route('admin-login2'));
            }
        }

        $form = new Form(include module_path('UserManagement') . '/Resources/forms/admin/login.php');

        try {
            $languages = $this->container->get('languages');
        } catch (\Exception $e) {
            $languages = [];
        }

        $pairs = [];

        if ($languages) {
            foreach ($languages as $language) {
                $pairs[$language->getCode()] = $language->getName();
            }
        }

        $form->language->setMultiOptions($pairs);

        if ($this->request->isPost()) {

            $post = Utils::arrayMapRecursive('trim', $this->request->getPost());

            if ($form->isValid($post)) {

                $model = new ModelUsers();
                $user = $model->login($post['username'], $post['password']);

                if ($user instanceof User) {

                    $model->storeInSession($user, ['step' => 1]);

                    $auth = Auth::getInstance()->getIdentity();
                    if (!config('auth.2fa') || ($auth && !$auth['user']->getAuth2fa())) {
                        (new TableUsers())->update(['lastLoginTime' => date('Y-m-d H:i:s')], ['id = ?' => $user->getId()]);
                    }

                    $data = [];

                    if (isset($post['language'])) {
                        $data['lang'] = $post['language'];
                    }

                    trigger('log', [
                        'message' => 'Login',
                        'context' => ['username' => $user->getUsername()],
                    ]);

                    return new RedirectResponse($backTo ?: route('admin-home', $data));

                } else {

                    $form->password->addError('Невалидни данни');
                }
            }
        }


        return ['form' => $form];
    }

    /**
     * @NotAuthorize
     */
    public function login2Action()
    {
        $backTo = $this->request->getParam('backTo');

        $identity = identity();

        if (!config('auth.2fa') || $identity) {
            return new RedirectResponse($backTo ?: route_reset('admin-home'));
        }

        $auth = Auth::getInstance()->getIdentity();

        if (!$auth || !isset($auth['step']) || $auth['step'] == 2 || !isset($auth['user']) || !$auth['user'] instanceof Identity) {
            return new RedirectResponse(route('admin-login'));
        }

        $auth2fa = $auth['user']->getAuth2fa();

        if (empty($auth2fa)) {
            return new RedirectResponse($backTo ?: route_reset('admin-home'));
        }

        if ($this->request->isPost()) {

            $btnBack = $this->request->getPost('btnBack');

            if ($btnBack) {
                Auth::getInstance()->clearIdentity();
                return new RedirectResponse(route('admin-login'));
            }

            $code = trim($this->request->getPost('code'));

            $success = false;

            switch ($auth2fa) {
                case 'google' :
                    $googleAuth = new GoogleAuthenticator();
                    if ($googleAuth->checkCode($auth['user']->getAuth2faSecret(), $code)) {
                        $success = true;
                    } else {
                        flash()->setMessage(translate('Невалиден код'), 'danger');
                    }
                    break;
                case 'email' :
                    if ((new Tokens())->checkCode($auth['user']->getId(), $code)) {
                        $success = true;
                    }
                    break;
            }

            if ($success) {
                Auth::getInstance()->setIdentity(['user' => $auth['user'], 'step' => 2]);
                (new TableUsers())->update(['lastLoginTime' => date('Y-m-d H:i:s')], ['id = ?' => $auth['user']->getId()]);
                trigger('log', [
                    'message' => 'Login 2FA',
                    'context' => ['username' => $auth['user']->getUsername(), 'auth2fa' => $auth2fa],
                ]);
                return new RedirectResponse($backTo ?: route_reset('admin-home'));
            }
        }

        $this->view->setTemplate('admin/index/login-' . $auth2fa . '-2fa');
    }

    /**
     * @NotAuthorize
     */
    public function sendCodeAction()
    {
        $backTo = $this->request->getParam('backTo');

        $identity = identity();

        if (!config('auth.2fa') || $identity) {
            return new RedirectResponse($backTo ?: route_reset('admin-home'));
        }

        $auth = Auth::getInstance()->getIdentity();

        if (!$auth || !isset($auth['step']) || $auth['step'] == 2 || !isset($auth['user']) || !$auth['user'] instanceof Identity) {
            return new RedirectResponse(route('admin-login'));
        }

        $auth2fa = $auth['user']->getAuth2fa();

        if (empty($auth2fa)) {
            return new RedirectResponse($backTo ?: route_reset('admin-home'));
        }

        if ($auth2fa != 'email') {
            return new RedirectResponse(route('admin-login'));
        }

        send_mail('auth-email', $auth['user']->getEmail(), ['code' => (new Tokens())->generateCode($auth['user']->getId())]);

        trigger('log', [
            'message' => '2FA send code to email',
            'context' => ['email' => $auth['user']->getEmail()],
        ]);

        return (new RedirectResponse(route('admin-login2')))->withFlash('Кодът е изпратен. Моля, проверете вашата електронна поща.');
    }

    /**
     * @NotAuthorize
     */
    public function generateSecretAction()
    {
        $identity = identity();

        if (empty($identity) || !$identity instanceof User) {
            return new RedirectResponse(route_reset('admin-login'));
        }

        switch ($identity->getAuth2fa()) {
            case 'google' :
                $identity->setAuth2faSecret((new GoogleAuthenticator())->generateSecret());
                break;
        }

        $this->getModel()->save($identity);
        $this->getModel()->storeInSession($identity, ['step' => config('auth.2fa') && $identity->getAuth2fa() ? 2 : 1]);

        trigger('log', [
            'message' => '2FA generate secret code',
            'context' => ['username' => $identity->getUsername()],
        ]);

        return (new RedirectResponse(route_reset('admin', ['module' => 'user-management', 'controller' => 'users', 'action' => 'profile'])))->withFlash(translate('Новият секретен ключ е генериран'));
    }

    public function logoutAction()
    {
        if (Auth::getInstance()->hasIdentity()) {
            (new TableUsers())->update(['lastLoginTime' => null, 'refreshTime' => null], ['id = ?' => identity()->getId()]);
            Auth::getInstance()->clearIdentity();
        }

        return new RedirectResponse(route_reset('admin-login'));
    }
}