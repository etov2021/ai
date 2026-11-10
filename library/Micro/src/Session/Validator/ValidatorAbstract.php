<?php

namespace Micro\Session\Validator;

abstract class ValidatorAbstract implements ValidatorInterface
{
    /**
     * SetValidData() - This method should be used to store the environment variables that
     * will be needed in order to validate the session later in the validate() method.
     * These values are stored in the session in the __MICRO namespace, in an array named VALID
     *
     * @param  mixed $data
     * @return void
     */
    protected function setValidData($data)
    {
        $validatorName = get_class($this);

        $_SESSION['__MICRO']['VALID'][$validatorName] = $data;
    }

    /**
     * GetValidData() - This method should be used to retrieve the environment variables that
     * will be needed to 'validate' a session.
     *
     * @return mixed
     */
    protected function getValidData()
    {
        $validatorName = get_class($this);

        if (isset($_SESSION['__MICRO']['VALID'][$validatorName])) {
            return $_SESSION['__MICRO']['VALID'][$validatorName];
        }

        return null;
    }
}