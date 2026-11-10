<?php

namespace Micro\Form\Element;

use Micro\Form\Element;
use Micro\Captcha\CaptchaBuilder;
use Micro\Session\SessionNamespace;

class Captcha extends Element
{
    /**
     * @var SessionNamespace
     */
    protected $session;

    /**
     * @var array
     */
    protected $captchaMessage = 'Стойността е невалидна';

    /**
     * @var array
     */
    protected $captchaOptions;

    /**
     * @return SessionNamespace
     */
    public function getSession()
    {
        if ($this->session === \null) {
            $this->session = new SessionNamespace($this->name . '_form_captcha');
        }

        return $this->session;
    }

    public function isValid($value, ?array $context = \null)
    {
        $this->setValue($value);

        if (empty($value) || $this->getSession()->value !== $value) {
            $this->addError($this->captchaMessage);
        }

        if ($this->hasErrors()) {
            return \false;
        }

        return \true;
    }

    public function setCaptchaMessage($message)
    {
        $this->captchaMessage = $message;
    }

    public function setCaptchaOptions(array $options)
    {
        $this->captchaOptions = $options;
    }

    public function render()
    {
        $tmp = '';

        $captcha = new CaptchaBuilder();

        foreach ($this->captchaOptions as $k => $v) {

            $method = 'set' . \ucfirst($k);

            if (method_exists($captcha, $method)) {
                $captcha->$method($v);
            }
        }

        $captcha->build();

        $this->getSession()->value = $captcha->getPhrase();

        $tmp .= '<div class="captcha-container captcha-container-' . $this->name . '">';

        $tmp .= '<img src="' . $captcha->inline() . '">';
        $tmp .= '<input type="text" name="' . $this->getFullyName() . '"' . $this->htmlAttributes() . '/>';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        $tmp .= '</div>';

        return $tmp;
    }
}