<?php

namespace Micro\Form\Element;

use Micro\Form\Element;

class GoogleCaptcha extends Element
{
    protected $captchaMessage = 'Google captcha invalid';

    protected $siteKey;

    protected $siteSecret;

    protected $lang;

    protected $alwaysValid = \false;

    public function setCaptchaMessage($message)
    {
        $this->captchaMessage = $message;
    }

    public function setSiteKey($key)
    {
        $this->siteKey = $key;
    }

    public function setSiteSecret($key)
    {
        $this->siteSecret = $key;
    }

    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    public function setAlwaysValid($value)
    {
        $this->alwaysValid = (bool) $value;
    }

    public function isValid($value, ?array $context = \null)
    {
        if ($this->alwaysValid) {
            return \true;
        }

        $value = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

        if (!empty($value)) {
            $response = $this->checkCaptcha($value);
            if ($response && isset($response['success']) && $response['success']) {
                return \true;
            }
        }

        $this->addError($this->captchaMessage);

        return \false;
    }

    public function render()
    {
        $tmp = '';

        $tmp .= '<div class="gcaptcha-container gcaptcha-container-' . $this->name . '">';

        $tmp .= '<script src="https://www.google.com/recaptcha/api.js?hl=' . $this->lang . '"></script>';
        $tmp .= '<div class="g-recaptcha" data-sitekey="' . $this->siteKey . '"></div>';

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        $tmp .= '</div>';

        return $tmp;
    }

    protected function checkCaptcha($value)
    {
        $data = \http_build_query([
            'secret'   => $this->siteSecret,
            'response' => $value,
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $data
            ]
        ];

        $context  = \stream_context_create($opts);

        return \json_decode(\file_get_contents('https://www.google.com/recaptcha/api/siteverify', \false, $context), \true);
    }
}