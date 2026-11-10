<?php

namespace Micro\Commerce\Payment\Processor\Adapter;

use Micro\Commerce\Payment\Processor\ProcessorAbstract;

class Paysera extends ProcessorAbstract
{
    protected $orderId;
    
    protected $currency = 'RON';
    
    protected $country = 'RO';
    
    protected $acceptUrl = '';
    
    protected $cancelUrl = '';
    
    protected $callbackUrl = '';
    
    protected $config = array();
    
    const ERR_CODES = [
        '0x1' => 'Payment amount is too small',
        '0x2' => 'Payment amount is too big',
        '0x3' => 'Selected currency is not available',
        '0x4' => 'Amount or currency is missing',
        '0x6' => 'projectId is missing or such ID does not exist',
        '0x7' => 'Testing mode is turned off, but test payment is being made',
        '0x8' => 'You have banned this way of payment',
        '0x9' => 'Coding of variable paytext is not suitable',
        '0x10' => 'Empty or not correctly filled-in orderid',
        '0x11x0' => 'Project is not checked',
        '0x11x2' => 'Project is suspended',
        '0x11x4' => 'Project is blocked',
        '0x11x5' => 'Project is deleted',
        '0x11x6' => 'Project is checked, but not confirmed',
        '0x13' => 'accepturl, cancelurl, callbackurl or referer address differs from the addresses confirmed in the project',
        '0x14' => 'Invalid "sign" parameter',
        '0x15x0' => 'At least one of these parameters is incorrect: cancelurl, accepturl, callbackurl',
        '0x15x1' => 'Parameter time_limit is not valid',
    ];
    
    /**
     * @return the $callbackUrl
     */
    public function getCallbackUrl()
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     */
    public function setCallbackUrl($callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;
    }

    /**
     * @return the $country
     */
    public function getCountry()
    {
        return $this->country;
    }
    
    /**
     * @param string $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }
    
    /**
     * @return the $customOrderId
     */
    public function getCustomOrderId()
    {
        return $this->customOrderId;
    }

    /**
     * @param field_type $customOrderId
     */
    public function setCustomOrderId($customOrderId)
    {
        $this->customOrderId = $customOrderId;
    }
    
    /**
     * @return the $orderId
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    /**
     * @param field_type $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }
    
    public function __construct(array $config)
    {
        $this->setConfig($config);
        
        if (isset($this->config['currency'])) {
            $this->currency = strtoupper($this->config['currency']);
        }
        
        return parent::__construct($config);
    }
    
    /**
     * @param string $index
     * @return multitype:|NULL
     */
    public function getConfig($index)
    {
        if (isset($this->config[$index])) {
            return $this->config[$index];
        }
        
        return null;
    }
    
    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }
    /**
     * @return string
     */
    public function getCurrency() {
        return $this->currency;
    }
    
    /**
     *
     * @param unknown $currency
     * @return \Micro\Commerce\Payment\Processor\Adapter\Paysera
     */
    public function setCurrency($currency)
    {
        $this->currency = strtoupper($currency);
        return $this;
    }
    
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }
    public function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;
        
        return $this;
    }
    
    public function getAcceptUrl()
    {
        return $this->acceptUrl;
    }
    public function setAcceptUrl($acceptUrl)
    {
        $this->acceptUrl = $acceptUrl;
        
        return $this;
    }
    
    function processPayment()
    {
        \WebToPay::redirectToPayment([
            'projectid' => $this->getConfig('projectId'),
            'sign_password' => $this->getConfig('password'),
            'orderid' => $this->getOrderId(),
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            'country' => $this->getCountry(),
            'lang' => $this->getCurrency(),
            'accepturl' => $this->getAcceptUrl(),
            'cancelurl' => $this->getCancelUrl(),
            'callbackurl' => $this->getCallbackUrl(),
            ], true);
        
    }
    
    public function responseHandler($params)
    {
        $response = new \stdClass();
        
        $response->error    = false;
        $response->response = null;
        $response->orderId  = null;
        $response->message  = '';
        
        if (count($params)) {
            try {
                $data = \WebToPay::validateAndParseData(
                    $params,
                    $this->getConfig('projectId'),
                    $this->getConfig('password')
                    );

                $response->response = json_encode($data);
                $response->orderId = isset($data['orderid']) ? $data['orderid'] : null;
                
                if ($data['status'] === '1' || $data['status'] === '3') {
                    return $response;
                } else {
                    $response->error = true;
                    
                    $response->message = 'Payment was not successful';
                }
            } catch (\Exception $exception) {
                $response->error = true;
                
                $response->message = 'Payment was not successful';
            }
            
        }
        
        return $response;
        
    }
    
    public function getResponse()
    {
        
    }
}