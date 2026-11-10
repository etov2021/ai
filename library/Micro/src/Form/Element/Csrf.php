<?php

namespace Micro\Form\Element;

use Micro\Form\Element;
use Micro\Session\SessionNamespace;
use Micro\Math\Rand;
use Micro\Http\Request;
use Micro\Container\SharedContainer;

class Csrf extends Element
{
    protected $errorMessage = 'Невалиден токен';

    /**
     * @var SessionNamespace
     */
    protected $session;

    /**
     * @var Request
     */
    protected $request;

    public function getSession()
    {
        if ($this->session === null) {
            $this->session = new SessionNamespace($this->name . '_form_csrf');
        }

        return $this->session;
    }

    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = SharedContainer::getInstance()->get('request');
        }

        return $this->request;
    }

    public function setErrorMessage($message)
    {
        $this->errorMessage = $message;
    }

    public function isValid($value, ?array $context = null)
    {
        $value = $value ?: $this->getRequest()->getHeaderLine('x-csrf-token');

        if ($this->getToken() !== $value) {
            $this->addError($this->errorMessage);
            return false;
        };

        return true;
    }

    public function render()
    {
        $tmp = '';

        $tmp .= '<input type="hidden" name="' . $this->getFullyName() . '" value="' . $this->generateToken() . '" />';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function generateToken()
    {
        $this->getSession()->token = md5(Rand::getString(40));

        return $this->getToken();
    }

    public function getToken()
    {
        return $this->getSession()->token ?? null;
    }
}