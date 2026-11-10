<?php

namespace Micro\Commerce\Payment\Processor\Adapter;

use Micro\Commerce\Payment\Processor\ProcessorException;
use Micro\Commerce\Payment\Processor\ProcessorAbstract;

class Borica extends ProcessorAbstract
{
    const RESPONSE_CODE_SUCCESS = '00';
    const RESPONSE_CODE_NOT_TRUSTED = -1;
    const TRANSACTION_CODE_PAY = 10;
    const TRANSACTION_CODE_AUTHORIZE = 21;

    protected $transactionType;
    protected $transactionCode = 10;
    protected $action;
    protected $terminalId;
    protected $signature;

    protected $defaultConfig = [
        'language' => 'BG',
        'currency' => 'BGN',
        'protocol' => '1.1',
    ];

    protected $config = [];

    // Set the allowed transaction types
    public static $transactionTypes = [10, 21, 22, 23, 31, 32, 33, 34, 40];

    // Set the response codes
    public static $responseCodes = [
        '00' => 'OK',
        '04' => 'The acquirer should instruct the merchant to retain the card. No specific reason given.',
        '13' => 'Expired card',
        '85' => 'Reversal already exists',
        '86' => 'Transaction already exists',
        '87' => 'Wrong protocol version',
        '88' => 'No BOReq parameter',
        '89' => 'Missing transaction',
        '90' => 'Invalid card',
        '91' => 'Timeout',
        '92' => 'Invalid eBorica request',
        '93' => 'Invalid 3D authentication',
        '94' => 'Canceled transaction',
        '95' => 'Invalid merchant signature',
        '96' => 'Technical error during transaction processing',
        '97' => 'Rejected transaction due to a froud rule',
        '98' => 'No BOResp for this BOReq',
        '99' => 'Authorization rejected by the TPSS'
    ];

    public static $protocols = ['1.0', '1.1', '2.0'];

    public static $languages = ['BG', 'EN'];

    public static $currencies = ['BGN', 'EUR'];

    public function __construct(array $config)
    {
        $this->config = $config + $this->defaultConfig;
    }

    /**
     * @param float|null $amount
     */
    public function addTransaction($amount = null)
    {
        if (empty($amount)) {
            $amount = $this->getAmount();
        }

        $this->action = 'registerTransaction';
        $this->transactionCode = $this->getTransactionCode();
        $this->amount = $amount * 100;
        $this->transactionId = $this->getTransactionId();
        $this->description = $this->getDescription();
    }

    protected function generateRequest()
    {
        $request = $this->getTransactionCode();
        $request .= date('YmdHis', time());
        $request .= str_pad($this->amount, 12, 0, STR_PAD_LEFT);
        $request .= $this->getConfig('terminalId');
        $request .= str_pad(substr($this->getTransactionId(),0,15), 15);
        $request .= str_pad(substr($this->getDescription(), 0, 125), 125);
        $request .= strtoupper((string) $this->getConfig('language'));
        $request .= (string) $this->getConfig('protocol');

        if ($this->getConfig('protocol') == '1.1') {
            $request .= strtoupper((string) $this->getConfig('currency'));
        }

        if ($this->getConfig('protocol') == '2.0') {
            $request .= str_pad((string) $this->getConfig('ott'), 6);
        }

        $this->signRequest($request);

        $request .= $this->signature;

        $request = urlencode(base64_encode($request));

        $this->checkRequestForErrors();

        $boricaUrl = (string) $this->getConfig('boricaUrl');

        $urlString = $boricaUrl . $this->getAction() . '?eBorica=' . $request;

        return $urlString;
    }

    protected function checkRequestForErrors()
    {
        if (!is_numeric($this->getConfig('terminalId')) || strlen($this->getConfig('terminalId')) != 8) {
            throw new ProcessorException('Invalid terminal id!');
        }

        if (!in_array($this->getTransactionCode(), self::$transactionTypes)) {
            throw new ProcessorException('Invalid transaction code!');
        }

        if ($this->getAmount() && (!is_numeric($this->getAmount()) || strlen($this->getAmount()) > 12)) {
            throw new ProcessorException('Invalid amount!');
        }

        if (strlen($this->getTransactionId()) > 15) {
            throw new ProcessorException('Invalid transaction id!');
        }

        if (strlen($this->getDescription()) > 125) {
            throw new ProcessorException('Invalid description!');
        }

        if (!in_array((string) $this->getConfig('protocol'), self::$protocols)) {
            throw new ProcessorException('Invalid protocol!');
        }

        if (!in_array(strtoupper((string) $this->getConfig('language')), self::$languages)) {
            throw new ProcessorException('Invalid language!');
        }

        if (!in_array(strtoupper((string) $this->getConfig('currency')), self::$currencies)) {
            throw new ProcessorException('Invalid currency!');
        }

        if (strlen($this->signature) != 128) {
            throw new ProcessorException('Invalid signature!');
        }
    }

    /**
     * @param bool $returnRequest
     * @return string|redirect
     */
    public function process($returnRequest = false)
    {
        $request = $this->generateRequest();

        if ($returnRequest) {
            return $request;
        }

        header('Location: ' . $request);

        exit(0);
    }

    /**
     * Sign the transaction request with the private key
     *
     * @param string $request Transaction's request
     * @return void
     */
    protected function signRequest($request)
    {
        if (!file_exists($this->getConfig('certificatePath')) || !is_readable($this->getConfig('certificatePath'))) {
            throw new ProcessorException('Invalid certificate file!');
        }

        $fp = fopen($this->getConfig('privateKeyPath'), "r");
        $privKey = fread($fp,8192);
        fclose($fp);

        $privateKeyId = openssl_pkey_get_private($privKey, $this->getConfig('privateKeyPass'));

        openssl_sign($request, $this->signature, $privateKeyId);
        openssl_free_key($privateKeyId);
    }

    /**
     * @param string $message
     * @throws ProcessorException
     * @return number
     */
    protected function signResponse($message)
    {
        if (!file_exists($this->getConfig('publicKeyAPGWPath')) || !is_readable($this->getConfig('publicKeyAPGWPath'))) {
            throw new ProcessorException('Invalid server certificate public key file!');
        }

        $fp = fopen($this->getConfig('publicKeyAPGWPath'),"r");
        $cert = fread($fp,8192);
        fclose($fp);

        $publicKeyId = openssl_get_publickey($cert);
        $response = openssl_verify(substr($message, 0, strlen($message)-128),substr($message, 56, 128), $publicKeyId);
        openssl_free_key($publicKeyId);

        return $response;
    }

    /**
     * @param string $message
     * @return mixed
     */
    public function parseResponse($message)
    {
        if ($message && $message !='null') {
            $message = base64_decode($message);
            return array(
                'transactionCode' => substr($message, 0, 2),
                'transactionTime' => substr($message, 2, 14),
                'amount' => substr($message, 16, 12),
                'terminalId' => substr($message, 28, 8),
                'transactionId' => substr($message, 36, 15),
                'responseCode' => substr($message, 51, 2),
                'protocolVersion' => substr($message, 53, 3),
                'signature' => substr($message, 56, 128),
                'signatureStatus' => 1//$this->signResponse($message),
            );
        }

        return null;
    }

    /**
     * @param string $url
     * @throws ProcessorException
     * @return mixed
     */
    public function getResponseBorica($url)
    {
        $ch = curl_init ();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        //curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->getConfig('serverETLogPath'));
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->getConfig('serverETLogPass'));
        curl_setopt($ch, CURLOPT_SSLKEY, $this->getConfig('privateKeyETLogPath'));
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD,$this->getConfig('privateKeyETLogPass'));

        $data = curl_exec($ch);

        if (curl_error($ch)) {
            throw new ProcessorException('CURL authentication failed! ' . curl_error($ch));
        }

        curl_close ($ch);

        return $data;
    }

    public function checkResponse($message)
    {
        $translactionResponse = new \stdClass();
        $translactionResponse->transactionId = null;
        $translactionResponse->code = null;
        $translactionResponse->status = null;
        $translactionResponse->message = null;

        if (empty($message)) {
            return $translactionResponse;
        }

        if (is_array($message)) {
            $responseData = $message;
        } else {
            $responseData = $this->parseResponse($message);
        }

        if ($responseData['signatureStatus'] !== 1) {
            $translactionResponse->message = 'Unknow response signature';
            $translactionResponse->transactionId = $responseData['transactionId'];
            $translactionResponse->code = -1;
            return $translactionResponse;
        }

        $translactionResponse->code = $responseData['responseCode'];
        $translactionResponse->transactionId = $responseData['transactionId'];

        if (isset(self::$responseCodes[$responseData['responseCode']])) {
            $translactionResponse->message = self::$responseCodes[$responseData['responseCode']];
        }

        return $translactionResponse;

    }

    protected function transactionInfoRequest($transactionId)
    {
        $this->action = 'transactionStatusReport';
        $this->transactionCode = 10;
        $this->transactionId = $transactionId;
    }

    /**
     * Get the transaction info
     *
     * @param string $transactionId Transaction unique id
     * @return string
     */
    public function getTransactionInfo($transactionId)
    {
        $this->transactionInfoRequest($transactionId);
        $request = $this->generateRequest();
        $response = $this->getResponseBorica($request);
        return $this->parseResponse($response);
    }

    public function setCancelTransactionInfo($transactionId, $amount, $description)
    {
        $this->action = 'manageTransaction';
        $this->transactionCode = 40;
        $this->transactionId = $transactionId;
        $this->description = $description;
        $this->amount = $amount * 100;
    }

    /**
     * Cancel transaction (Reversal)
     *
     * @param string $transaction_id Transaction id
     * @param integer $amount Amount
     * @param string $description Transaction description
     * @return string
     */
    public function cancelTransaction($transactionId, $amount, $description = 'Cancel transaction')
    {
        $this->setCancelTransactionInfo($transactionId, $amount, $description);

        $request = $this->generateRequest();

        $response = $this->getResponseBorica($request);

        return $this->parseResponse($response);
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
    * @param string $k
    * @param mixed $v
    */
    public function setConfig($k, $v)
    {
        $this->config[$k] = $v;
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Commerce\Payment\Processor\ProcessorInterface::processPayment()
     */
    public function processPayment()
    {
        $this->addTransaction();

        return $this->process(true);
    }

    public function getResponse()
    {

    }

    /**
     * @return the $transactionType
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }

    /**
     * @param field_type $transactionType
     */
    public function setTransactionType($transactionType)
    {
        $this->transactionType = $transactionType;
    }

    /**
     * @return the $transactionCode
     */
    public function getTransactionCode()
    {
        return $this->transactionCode;
    }

    /**
     * @param number $transactionCode
     */
    public function setTransactionCode($transactionCode)
    {
        $this->transactionCode = $transactionCode;
    }

    /**
     * @return the $action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return the $terminalId
     */
    public function getTerminalId()
    {
        return $this->terminalId;
    }

    /**
     * @param field_type $terminalId
     */
    public function setTerminalId($terminalId)
    {
        $this->terminalId = $terminalId;
    }
}