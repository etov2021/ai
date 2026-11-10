<?php

namespace Micro\Session\Validator;

class HttpUserAgent extends ValidatorAbstract
{
    /**
     * Setup() - this method will get the current user agent and store it in the session
     * as 'valid data'
     *
     * @return void
     */
    public function setup()
    {
        $this->setValidData( (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) );
    }

    /**
     * Validate() - this method will determine if the current user agent matches the
     * user agent we stored when we initialized this variable.
     *
     * @return bool
     */
    public function validate()
    {
        $currentBrowser = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null);

        return $currentBrowser === $this->getValidData();
    }
}