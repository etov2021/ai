<?php

namespace Micro\Commerce\Payment\Processor;

use Micro\Commerce\Payment\Processor\ProcessorInterface;

abstract class ProcessorAbstract implements ProcessorInterface
{
    protected $transactionId;

    /**
     * Amount that has to be paid
     * @var float
     */
    protected $amount = 0;

    /**
     * @var string
     */
    protected $description;

    /**
     * Payment status
     * @var mixed
     */
    protected $status;

    /**
     * Error messages
     * @var array
     */
    protected $errors = [];

	/**
     * Get the last error message
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->setOptions($config);
        }
    }

    /**
     * @param mixed $transactionId
     * @return \Micro\Commerce\Payment\Processor\ProcessorAbstract
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     *
     * @return unknown
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param array $config
     * @return ProcessorAbstract
     */
    protected function setOptions(array $config)
    {
        foreach ($config as $key => $value) {
            $method = 'set' . \ucfirst($key);
            if (\method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return $this;
    }

	/**
     *  Get the status of the payment
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *  Set the status of the payment
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

	/**
	 * Is the transaction successfull
     * @return boolean
     */
    public function isError()
    {
        return (\count($this->errors) > 0);
    }

    /**
     * Set errors array
     * @param array $errors
     * @return ProcessorAbstract
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Add error
     * @param string $error
     * @return ProcessorAbstract
     */
    public function addError($error)
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Clear all errors
     * @return ProcessorAbstract
     */
    public function clearErrors()
    {
        $this->errors = [];

        return $this;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }


    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Execute callbacks
     * @param $callback
     * @param $params
     */
    public function doCallback($callback, array $params)
    {
        $method = 'on' . \ucfirst($callback);

        if (!method_exists($this, $method)) {
            return;
        }

        return $this->$method($params);
    }
}