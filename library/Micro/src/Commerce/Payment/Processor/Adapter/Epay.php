<?php

namespace Micro\Commerce\Payment\Processor;

class Epay extends ProcessorAbstract
{
    protected $min;
    protected $secret;
    protected $invoice;
	protected $actionUrl;
	protected $okUrl;
	protected $cancelUrl;
	protected $descr = '...';

	public function processPayment()
	{

	}

    public function getInfoConfig()
    {
        $actionUrl = $this->getActionUrl();

        if (empty($actionUrl)) {
            if (is_debug()) {
                $actionUrl = 'https://devep2.datamax.bg/ep2/epay2_demo/';
            } else {
                $actionUrl = 'https://www.epay.bg/';
            }
        }

        return [
            'action' => $actionUrl,
            'method' => 'POST',
            'id'     => 'epayForm',
            'elements' => [
                'PAGE' => [
                    'type' => 'hidden',
                    'options' => [
                        'value' => 'paylogin',
                    ],
                ],
                'ENCODED' => [
                    'type' => 'hidden',
                    'options' => [
                        'value' => $this->getEncodedData(),
                    ],
                ],
                'CHECKSUM' => [
                    'type' => 'hidden',
                    'options' => [
                        'value' => $this->getChecksum(),
                    ],
                ],
            ],
        ];
    }

	/**
	 * {@inheritDoc}
	 * @see \Micro\Commerce\Processor\ProcessorInterface::getReceipt()
	 */
    public function getReceipt($response = \null)
    {
        return \null;
    }

	/**
	 * {@inheritDoc}
	 * @see \Micro\Commerce\Processor\ProcessorInterface::getResponse()
	 */
    public function getResponse()
    {
        return \null;
    }

	/**
     * @param float $amount
     */
    public function pay($amount)
    {
        return \false;
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Commerce\Processor\ProcessorInterface::getName()
     */
	public function getName()
    {
        return "E-Pay.bg";
    }

	/**
	 * {@inheritDoc}
	 * @see \Micro\Commerce\Processor\ProcessorInterface::getVersion()
	 */
    public function getVersion()
    {
        return "0.0.1";
    }

    /**
     * Get post data string
     *
     * @return string
     */
    public function getData()
    {
        $data = array();

        $invoice = $this->getInvoice() ? $this->getInvoice() : time();

        $data['MIN'] = 'MIN=' . $this->getMin();
        $data['INVOICE'] = 'INVOICE=' . $invoice;
        $data['EXP_TIME'] = 'EXP_TIME=' . \date('d.m.Y');
        $data['AMOUNT'] = 'AMOUNT=' . $this->getAmount();
        $data['DESCR'] = 'DESCR=' . $this->getDescr();

        if ($this->getOkUrl()) {
        	$data['URL_OK'] = 'URL_OK=' . $this->getOkUrl();
        }

        if ($this->getCancelUrl()) {
        	$data['URL_CANCEL'] = 'URL_CANCEL=' . $this->getCancelUrl();
        }

        return \implode("\n", $data);
    }

    public function getEncodedData()
    {
        return \base64_encode($this->getData());
    }

    /**
     * Calculate data checksum
     * @return string
     */
    public function getChecksum()
    {
        return $this->hmac('sha1', $this->getEncodedData(), $this->secret);
    }

    /**
     * @param string $algo
     * @param string $data
     * @param string $passwd
     */
    protected function hmac($algo, $data, $passwd)
    {
        $algo = \strtolower($algo);

        $p = ['md5' => 'H32','sha1' => 'H40'];

        if (\strlen($passwd) > 64) {
            $passwd = \pack($p[$algo], $algo($passwd));
        }

        if (\strlen($passwd) < 64) {
            $passwd = \str_pad($passwd, 64, \chr(0));
        }

        $ipad = \substr($passwd,0,64) ^ \str_repeat(\chr(0x36), 64);
        $opad = \substr($passwd,0,64) ^ \str_repeat(\chr(0x5C), 64);

        return ($algo($opad . \pack($p[$algo], $algo($ipad . $data))));
    }

    protected function onResponse(array $params)
    {
		$result   = '';

		$response = new \stdClass();
        $response->status = 'WAITING';

		if ($this->hmac('sha1', $params['encoded'], $params['secret']) == $params['checksum']) { # XXX Check if the received CHECKSUM is OK

		    $data = \base64_decode($params['encoded']);

		    foreach (\explode("\n", $data) as $line) {

		        if (\preg_match('~^INVOICE=(\d+):STATUS=(PAID|DENIED|EXPIRED)(:PAY_TIME=(\d+):STAN=(\d+):BCODE=([0-9a-zA-Z]+))?$~', $line, $regs)) {

		            $invoice  = $regs[1];
		            $status   = $regs[2];
		            $pay_date = $regs[4]; # XXX if PAID
		            $stan     = $regs[5]; # XXX if PAID
		            $bcode    = $regs[6]; # XXX if PAID

		            # XXX process $invoice, $status, $pay_date, $stan, $bcode here
		            $response->invoice = $invoice;
		            $response->status  = $status;

		            # XXX if OK for this invoice
		            $result .= "INVOICE=$invoice:STATUS=OK\n";
		        }
		    }

		} else {

		    $response->status = 'ERROR CHECKSUM';

		    $result .= "ERR=Not valid CHECKSUM\n"; # XXX The description of error is REQUIRED
		}

		$this->setStatus($response);

		return $result;
    }

    protected function getActionUrl()
    {
        return $this->actionUrl;
    }

    protected function setActionUrl($actionUrl)
    {
        $this->actionUrl = $actionUrl;
    }

    protected function getDescr()
    {
        return $this->descr;
    }

    protected function setDescr($descr)
    {
        $this->descr = $descr;
    }

    protected function getOkUrl()
    {
        return $this->okUrl;
    }

    protected function setOkUrl($okUrl)
    {
        $this->okUrl = $okUrl;
    }

    protected function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    protected function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;
    }

    protected function getMin()
    {
        return $this->min;
    }

    protected function setMin($min)
    {
        $this->min = $min;

        return $this;
    }

    protected function getSecret()
    {
        return $this->secret;
    }

    protected function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;

        return $this;
    }
}