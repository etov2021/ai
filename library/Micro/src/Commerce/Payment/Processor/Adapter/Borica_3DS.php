<?php

namespace Micro\Commerce\Payment\Processor\Adapter;

use Micro\Commerce\Payment\Processor\ProcessorAbstract;

class Borica_3DS extends ProcessorAbstract
{
    const SUCCECSSFUL_CODE = "00";

    const ERR_GWP_CODES = [
        -1 => 'A mandatory request field is not filled in',
        -3 => "Acquirer host (NS) does not respond or wrong format of e-gateway response template file",
        -4 => 'No connection to the acquirer host (NS)',
        -11 => 'Error in the "Currency" request field',
        -12 => 'Error in the "Merchant ID" request field',
        -15 => 'Error in the "RRN" request field',
        -17 => 'Грешка при проверка на P_SIGN',
        -19 => 'Error in the authentication information request or authentication failed',
        -20 => 'A permitted time interval (1 hour by default) between the transaction Time Stam prequest field and the e-Gateway time is exceeded',
        -21 => 'The transaction has already been executed',
        -25 => 'Transaction canceled (e.g. by user)',
        -27 => 'Invalid merchant name',
        -32 => 'Duplicate declined transaction',
    ];

    const ERR_PAYPROCCESS_CODES = [
        null => "Timeout",
        01 => "Refer to card issuer",
        04 => "Pick Up",
        05 => "Do not Honour",
        13 => 'Invalid amount',
        30 =>  "Format error",
        65 =>  "Soft Decline",
        91 => 'Issuer or switch is inoperative',
        96 =>  "System Malfunction",
    ];

    protected $orderId;

    protected $email;

    protected $currency = 'BGN';

    protected $actionUrl = '';

    protected $returnUrl = '';

    protected $config = array();

    protected $country = 'BG';
    protected $rrn = '';
    protected $intRef = '';
    protected $customOrderId;

    protected $params = [];

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
     * @return the $rrn
     */
    public function getRrn()
    {
        return $this->rrn;
    }

    /**
     * @return the $intRef
     */
    public function getIntRef()
    {
        return $this->intRef;
    }

    /**
     * @param string $rrn
     */
    public function setRrn($rrn)
    {
        $this->rrn = $rrn;
    }

    /**
     * @param string $intRef
     */
    public function setIntRef($intRef)
    {
        $this->intRef = $intRef;
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
     * @return the $email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param field_type $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
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
     * @return \Micro\Commerce\Payment\Processor\Adapter\Borica
     */
    public function setCurrency($currency)
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    public function getActionUrl()
    {
        return $this->actionUrl;
    }

    public function setActionUrl($actionUrl)
    {
        $this->actionUrl = $actionUrl;

        return $this;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function generate_signature_message_from_TRYTIPE_1($timestamp, $nonce)
    {
        $message = strlen($this->getConfig('terminalId')) . "{$this->getConfig('terminalId')}";
        $message .= "11";
        $message .= strlen($this->getAmount()) . "{$this->getAmount()}";
        $message .= strlen($this->getCurrency()) . "{$this->getCurrency()}";
        $message .= strlen($this->getOrderId()) . "{$this->getOrderId()}";
        $message .= strlen($timestamp) . "{$timestamp}";
        $message .= strlen($nonce) . "{$nonce}";
        if (isset($this->params['MERCH_TOKEN_ID'])) {
            $tokenId = $this->params['MERCH_TOKEN_ID'];
            $message .= (string) strlen($tokenId) . (string) $tokenId;
        } else {
            $message .= '-';
        }
        return $message;
    }

    protected function generate_CUST_BOR_ORDER_ID($divider = "ORD@")
    {
        return "{$this->getOrderId()}{$divider}" . rand(10000, 99999);
    }

    public function processPayment()
    {
        $timestamp = gmdate('YmdHis');

        $currency = $this->getCurrency();

        $nonce = $this->getRandomHex();

        $message = $this->generate_signature_message_from_TRYTIPE_1($timestamp, $nonce);

        $privateKey = file_get_contents($this->getConfig('privateKeyPath'));

        $pkeyid = openssl_get_privatekey($privateKey, $this->getConfig('privateKeyPass'));

        openssl_sign($message, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkeyid);

        $form["AMOUNT"]     = $this->getAmount();
        $form["CURRENCY"]   = $currency;
        $form["DESC"]       = $this->getDescription();
        $form["TERMINAL"]   = $this->getConfig('terminalId');
        $form["MERCH_NAME"] = $this->getConfig('merchantName');
        $form["MERCH_URL"]  = $this->getConfig('merchantUrl');
        $form["MERCHANT"]   = $this->getConfig('merchantId');
        $form["EMAIL"]      = $this->getEmail();
        $form["TRTYPE"]     = 1;

        $form["ORDER"]                  = $this->getOrderId();
        $form["AD.CUST_BOR_ORDER_ID"]   = $this->getCustomOrderId() ? $this->getCustomOrderId() : $this->generate_CUST_BOR_ORDER_ID();
        $form["COUNTRY"]                = $this->getCountry();
        $form["TIMESTAMP"]              = $timestamp;
        $form["MERCH_GMT"]              = '+02';
        $form["NONCE"]                  = $nonce;
        $form["LANG"]               	= $this->getCountry();
        //$form["BACKREF"]                = $this->getReturnUrl();
        $form["ADDENDUM"]               = 'AD,TD';
        $form["P_SIGN"]                 = strtoupper(bin2hex($signature));

        /**
         * MK_TOKEN
         * MERCH_TRAN_STATE
         * MERCH_RN_ID
         * MERCH_TOKEN_ID
         * M_INFO
         */
        foreach ($this->params as $k => $v) {
            switch ($k) {
                case 'MK_TOKEN':
                    if ($v != 'MERCH') {
                        throw new \Exception('Borica field MK_TOKEN must be MERCH');
                    }
                    break;
                case 'MERCH_RN_ID':
                    $v = str_pad($v, 16, '0', STR_PAD_LEFT);
                    break;
                case 'MERCH_TRAN_STATE':
                    $v = strtoupper($v);
                    if (!in_array($v, ['S', 'M', 'C'])) {
                        throw new \Exception('Borica field MERCH_TRAN_STATE must be S, M or C');
                    }
                    break;
                case 'M_INFO':
                    $v = base64_encode(json_encode($v));
                    break;
            }
            $form[$k] = $v;
        }

        $formString = '<form action="' . $this->getConfig('actionUrl') . '" method="post" id="borica_payment_form">';

        foreach ($form as $key => $value) {
            $formString .= '<input type="hidden" name="'.$key.'" value="'.$value .'"/>' . "\n";
        }

        $formString .='<script type="text/javascript">
                        document.getElementById("borica_payment_form").submit();
					</script>
            </form>';

        return $formString;

    }

    public function getRandomHex($num_bytes = 16)
    {
        return strtoupper(bin2hex(openssl_random_pseudo_bytes($num_bytes)));
    }

    public function responseHandler($params)
    {
        $response = new \stdClass();

        $response->error    = false;
        $response->code     = $params['RC'] ?? null;
        $response->orderId  = $params['ORDER'] ?? null;
        $response->response = json_encode($params);
        $response->message  = $params['STATUSMSG'] ?? null;

        $response->rrn      = $params['RRN'] ?? null;
        $response->intRef   = $params['INT_REF'] ?? null;

        if (count($params)) {

            if (!isset($params['RC'])) {
                $response->error = true;
                return $response;
            }

            if (array_key_exists($params['RC'], self::ERR_GWP_CODES)) {
                $response->error = true;
                $response->message = self::ERR_GWP_CODES[$params['RC']];
                return $response;
            }

            if ($this->checkSignature($params)) {
                if ($params['RC'] === self::SUCCECSSFUL_CODE) {
                    $response->message = translate('Плащането е успешно');
                    $response->error = false;
                } elseif (array_key_exists($params['RC'], self::ERR_PAYPROCCESS_CODES)) {
                    $response->message = translate(self::ERR_PAYPROCCESS_CODES[$params['RC']]);
                    $response->error = true;
                } else {
                    $response->message = translate("Не дефиниранo RC {$params['RC']}");
                    $response->error = true;
                }
            } else {
                $response->message = translate('Грешка при продписване на сертификата');
                $response->error = true;
            }
        }

        return $response;
    }

    public function cancelTransaction()
    {
        $response = new \stdClass();

        $timestamp = gmdate('YmdHis');

        $currency = $this->getCurrency();

        $nonce = $this->getRandomHex();

        $message = $this->generate_signature_message_from_TRYTIPE_1($timestamp, $nonce);

        $privateKey = file_get_contents($this->getConfig('privateKeyPath'));

        $pkeyid = openssl_get_privatekey($privateKey, $this->getConfig('privateKeyPass'));

        openssl_sign($message, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkeyid);

        $form["TERMINAL"]   = $this->getConfig('terminalId');
        $form["TRTYPE"]     = 24;
        $form["AMOUNT"]     = $this->getAmount();
        $form["CURRENCY"]   = $currency;
        $form["ORDER"]      = $this->getOrderId();
        $form["DESC"]       = $this->getDescription();
        $form["MERCHANT"]   = $this->getConfig('merchantId');
        $form["MERCH_NAME"] = $this->getConfig('merchantName');
        $form["MERCH_URL"]  = $this->getConfig('merchantUrl');
        $form["EMAIL"]      = $this->getEmail();
        $form["COUNTRY"]    = $this->getCountry();
        $form["MERCH_GMT"]  = '+02';
        $form["LANG"]       = $this->getCountry();
        $form["ADDENDUM"]   = 'AD,TD';
        $form["AD.CUST_BOR_ORDER_ID"]   = $this->generate_CUST_BOR_ORDER_ID();
        $form["TIMESTAMP"]              = $timestamp;
        $form["NONCE"]                  = $nonce;
        $form["P_SIGN"]                 = strtoupper(bin2hex($signature));

        $form['RRN']        = $this->getRrn();
        $form['INT_REF']    = $this->getIntRef();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getConfig('actionUrl'));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $responseCurl = curl_exec($ch);

        if (curl_error($ch)) {
            $response->message = translate(curl_error($ch));

            $response->error = true;

            return $response;
        }

        curl_close($ch);

        $responseCurl = json_decode($responseCurl, true);

        if ($responseCurl['RC'] === self::SUCCECSSFUL_CODE) {
            $response->error = false;

        } elseif(array_key_exists($responseCurl['RC'], self::ERR_GWP_CODES)) {
            $response->message = translate(self::ERR_GWP_CODES[$responseCurl['RC']]);

            $response->error = true;

        } elseif(array_key_exists($responseCurl['RC'], self::ERR_PAYPROCCESS_CODES)) {
            $response->message = translate(self::ERR_PAYPROCCESS_CODES[$responseCurl['RC']]);

            $response->error = true;

        } else {
            $response->message = translate("Не дефиниранo RC {$responseCurl['RC']}");

            $response->error = true;
        }

        return $response;

    }

    public function getResponse()
    {

    }

    public function removeToken($tokenId, $id = null)
    {
        $timestamp = gmdate('YmdHis');

        $nonce = $this->getRandomHex();

        $orderId = $id ? substr(str_pad((string) $id, 6, '0', STR_PAD_LEFT), 0, 6) : $this->getRandomHex(6);

        $message = $this->generateSignatureMessageForType82($timestamp, $nonce, $orderId, $tokenId);

        $privateKey = file_get_contents($this->getConfig('privateKeyPath'));

        $pkeyid = openssl_get_privatekey($privateKey, $this->getConfig('privateKeyPass'));

        openssl_sign($message, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkeyid);

        $form['TERMINAL'] = $this->getConfig('terminalId');
        $form['TRTYPE'] = 82;
        $form['ORDER'] = $orderId;
        $form['TIMESTAMP'] = $timestamp;
        $form['NONCE'] = $nonce;
        $form['MERCH_TOKEN_ID'] = $tokenId;
        $form['P_SIGN'] = strtoupper(bin2hex($signature));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getConfig('actionUrl'));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        curl_close($ch);

        $response = json_decode($response, true);

        $data = [
            'success' => false,
            'message' => 'Грешка при изтриване на банковата карта',
            'response' => $response,
        ];

        if ($response) {
            $rc = $response['RC'] ?? null;
            if ($rc == self::SUCCECSSFUL_CODE) {
                $check = $this->checkSignature($response);
                if ($check) {
                    $data['success'] = true;
                    $data['message'] = '';
                }
            }

        }

        return $data;
    }

    protected function checkSignature(array $params)
    {
        $action = strlen($params["ACTION"]) > 0 ? strlen($params["ACTION"]).$params["ACTION"] : "-";
        $rc = strlen($params["RC"]) > 0 ? strlen($params["RC"]).$params["RC"] : "-";
        $approval = strlen($params["APPROVAL"]) > 0 ? strlen($params["APPROVAL"]).$params["APPROVAL"] : "-";
        $terminal = strlen($params["TERMINAL"]) > 0 ? strlen($params["TERMINAL"]).$params["TERMINAL"] : "-";
        $trtype = strlen($params["TRTYPE"]) > 0 ? strlen($params["TRTYPE"]).$params["TRTYPE"] : "-";
        $amount = strlen($params["AMOUNT"]) > 0 ? strlen($params["AMOUNT"]).$params["AMOUNT"] : "-";
        $currency = strlen($params["CURRENCY"]) > 0 ? strlen($params["CURRENCY"]).$params["CURRENCY"] : "-";
        $order = strlen($params["ORDER"]) > 0 ? strlen($params["ORDER"]).$params["ORDER"] : "-";
        $rrn  = strlen($params["RRN"]) > 0 ? strlen($params["RRN"]).$params["RRN"] : "-";
        $intRef = strlen($params["INT_REF"]) > 0 ? strlen($params["INT_REF"]).$params["INT_REF"] : "-";
        $paresStatus= strlen($params["PARES_STATUS"]) > 0 ? strlen($params["PARES_STATUS"]).$params["PARES_STATUS"] : "-";
        $eci = strlen($params["ECI"]) > 0 ? strlen($params["ECI"]).$params["ECI"] : "-";
        $timestamp = strlen($params["TIMESTAMP"]) > 0 ? strlen($params["TIMESTAMP"]).$params["TIMESTAMP"] : "-";
        $nonce = strlen($params["NONCE"]) > 0 ? strlen($params["NONCE"]).$params["NONCE"] : "-";
        $tokenId = isset($params["MERCH_TOKEN_ID"]) && strlen($params["MERCH_TOKEN_ID"]) > 0 ? strlen($params["MERCH_TOKEN_ID"]).$params["MERCH_TOKEN_ID"] : "-";
        $pSign = hex2bin($params['P_SIGN']);

        $data = $action.$rc.$approval.$terminal.$trtype.$amount.$currency.$order.$rrn.$intRef.$paresStatus.$eci.$timestamp.$nonce.$tokenId;

        $publicKey = file_get_contents($this->getConfig('publicKeyAPGWPath'));

        if (strpos($publicKey, 'CERTIFICATE') !== false) {
            $pkeyid = openssl_get_publickey($publicKey);
        } else {
            $pkeyid = $publicKey;
        }

        return openssl_verify($data, $pSign, $pkeyid, OPENSSL_ALGO_SHA256);
    }

    protected function generateSignatureMessageForType82($timestamp, $nonce, $orderId, $tokenId)
    {
        $message = strlen($this->getConfig('terminalId')) . "{$this->getConfig('terminalId')}";
        $message .= '282';
        $message .= '-'; // amount
        $message .= '-'; // currency
        $message .= strlen($orderId) . "{$orderId}";
        $message .= strlen($timestamp) . "{$timestamp}";
        $message .= strlen($nonce) . "{$nonce}";
        $message .= (string) strlen($tokenId) . (string) $tokenId;

        return $message;
    }
}