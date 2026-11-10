<?php

namespace Micro\Commerce\Payment\Processor\Adapter;

use Micro\Commerce\Payment\Processor\ProcessorAbstract;

class Alpha extends ProcessorAbstract
{
    const RESPONSE_CODE_SUCCESS = 'CAPTURED';

    protected $currency = 'EUR';
    protected $config = array();
    protected $orderId;
    protected $confirmUrl;
    protected $cancelUrl;
    protected $country = 'GR';
    protected $amount;
    protected $city;
    protected $zip;
    protected $addr;
    protected $language;

    /**
     * @return the $language
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @return the $city
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return the $zip
     */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * @return the $addr
     */
    public function getAddr()
    {
        return $this->addr;
    }

    /**
     * @param field_type $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @param field_type $zip
     */
    public function setZip($zip)
    {
        $this->zip = $zip;
    }

    /**
     * @param field_type $addr
     */
    public function setAddr($addr)
    {
        $this->addr = $addr;
    }

    /**
     * @return the $amount
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param number $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
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

    public function getResponse()
    {

    }

    public function setLanguage($language)
    {
        $this->language = strtolower($language);
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
     * @return string
     */
    public function getCurrency() {
        return $this->currency;
    }

    /**
     *
     * @param unknown $currency
     * @return \Micro\Commerce\Payment\Processor\Adapter\Alpha
     */
    public function setCurrency($currency)
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function checkResponse($params)
    {

        $translactionResponse = new \stdClass();

        $translactionResponse->status        = isset($params['status']) ? $params['status'] : null;
        $translactionResponse->transactionId = isset($params['txId']) ? $params['txId'] : null;
        $translactionResponse->orderId       = isset($params['orderid']) ? $params['orderid'] : null;
        $translactionResponse->message       = isset($params['message']) ? $params['message'] : null;

        $translactionResponse->response       = count($params) ? json_encode($params) : null;

        $translactionResponse->response       = count($params) ? json_encode($params) : null;

        return $translactionResponse;

    }


    public function checkResponse2($params)
    {

        $translactionResponse = new \stdClass();

        $translactionResponse->status        = isset($params['status']) ? $params['status'] : null;

        if(!isset($params['digest'])) {
            $translactionResponse->status = null;

            return $translactionResponse;
        }

        $postАrray = array();

        if (isset($params['version'])) {
            $postАrray[0] = $params['version'];
        }

        if (isset($params['mid'])) {
            $postАrray[1] = $params['mid'];
        }

        if (isset($params['orderid'])) {
            $postАrray[2] = $params['orderid'];
        }

        if (isset($params['status'])) {
            $postАrray[3] = $params['status'];
        }

        if (isset($params['orderAmount'])) {
            $postАrray[4] = $params['orderAmount'];
        }

        if (isset($params['currency'])) {
            $postАrray[5] = $params['currency'];
        }

        if (isset($params['paymentTotal'])) {
            $postАrray[6] = $params['paymentTotal'];
        }

        if (isset($params['message'])) {
            $postАrray[7] = $params['message'];
        }

        if (isset($params['riskScore'])) {
            $postАrray[8] = $params['riskScore'];
        }

        if (isset($params['payMethod'])) {
            $postАrray[9] = $params['payMethod'];
        }

        if (isset($params['txId'])) {
            $postАrray[10] = $params['txId'];
        }

        if (isset($params['paymentRef'])) {
            $postАrray[11] = $params['paymentRef'];
        }

        if (isset($params['extToken'])) {
            $postАrray[12] = $params['extToken'];
        }

        if (isset($params['extTokenPanEnd'])) {
            $postАrray[13] = $params['extTokenPanEnd'];
        }

        if (isset($params['extTokenExp'])) {
            $postАrray[14] = $params['extTokenExp'];
        }

        if (isset($params['extData'])) {
            $postАrray[15] = $params['extData'];
        }

        $postАrray[16] = $this->getConfig('privateKeyPass');

        $postData = implode("", $postАrray);

        $digest = base64_encode(hash("sha256", $postData, true));

        $postDigest = $params['digest'];

        if($postDigest != $digest) {

            $translactionResponse->status = null;

            return $translactionResponse;
        }

        $translactionResponse->transactionId = isset($params['txId']) ? $params['txId'] : null;
        $translactionResponse->orderId       = isset($params['orderid']) ? $params['orderid'] : null;

        return $translactionResponse;

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
     * @return the $options
     */
    public function getOptions ()
    {
        return $this->options;
    }

    public function processPayment()
    {
        $form_data = "";
        $form_data_array = array();

        if (is_debug()) {
            $form_order_amount = 0.1;	               $form_data_array[7] = $form_order_amount;		//Req
            $form_bill_zip = 'test';		           $form_data_array[13] = $form_bill_zip;			//Opt
            $form_bill_city = 'test';		           $form_data_array[14] = $form_bill_city;			//Opt
            $form_bill_addr = 'test';		           $form_data_array[15] = $form_bill_addr;			//Opt
        } else {
            $form_order_amount = $this->getAmount();   $form_data_array[7] = $form_order_amount;		//Req
            $form_bill_zip = $this->getZip();		   $form_data_array[13] = $form_bill_zip;			//Opt
            $form_bill_city = $this->getCity();		   $form_data_array[14] = $form_bill_city;			//Opt
            $form_bill_addr = $this->getAddr();		   $form_data_array[15] = $form_bill_addr;			//Opt
        }

        $form_version = "2";						   $form_data_array[1] = $form_version;				//Req
        $form_order_id = $this->getOrderId();          $form_data_array[5] = $form_order_id;			//Req
        $form_mid = $this->getConfig('mId');	       $form_data_array[2] = $form_mid;					//Req
        $form_lang = $this->getLanguage();			   $form_data_array[3] = $form_lang;				//Opt
        $form_device_cate = ""; 					   $form_data_array[4] = $form_device_cate;			//Opt
        $form_order_desc = "";						   $form_data_array[6] = $form_order_desc;			//Opt
        $form_currency = $this->getCurrency();		   $form_data_array[8] = $form_currency;			//Req
        $form_email = "";		                       $form_data_array[9] = $form_email;				//Req
        $form_phone = "";							   $form_data_array[10] = $form_phone;				//Opt
        $form_bill_country = $this->getCountry();	   $form_data_array[11] = $form_bill_country;		//Opt
        $form_bill_state = "";						   $form_data_array[12] = $form_bill_state;			//Opt

        $form_weight = "";							   $form_data_array[16] = $form_weight;				//Opt
        $form_dimension = "";						   $form_data_array[17] = $form_dimension;			//Opt
        $form_ship_counrty = "";					   $form_data_array[18] = $form_ship_counrty;		//Opt
        $form_ship_state = "";						   $form_data_array[19] = $form_ship_state;			//Opt
        $form_ship_zip = "";						   $form_data_array[20] = $form_ship_zip;			//Opt
        $form_ship_city = "";						   $form_data_array[21] = $form_ship_city;			//Opt
        $form_ship_addr = "";					       $form_data_array[22] = $form_ship_addr;			//Opt
        $form_add_fraud_score = "";			           $form_data_array[23] = $form_add_fraud_score;	//Opt
        $form_max_pay_retries = "";			           $form_data_array[24] = $form_max_pay_retries;	//Opt
        $form_reject3dsU = "";					       $form_data_array[25] = $form_reject3dsU;			//Opt
        $form_pay_method = "";						   $form_data_array[26] = $form_pay_method;			//Opt
        $form_trytpe = "";							   $form_data_array[27] = $form_trytpe;				//Opt
        $form_ext_install_offset = "";	               $form_data_array[28] = $form_ext_install_offset;	//Opt
        $form_ext_install_period = "";	               $form_data_array[29] = $form_ext_install_period;	//Opt
        $form_ext_reccuring_freq = "";	               $form_data_array[30] = $form_ext_reccuring_freq;	//Opt
        $form_ext_reccuring_enddate = "";              $form_data_array[31] = $form_ext_reccuring_enddate;//Opt
        $form_block_score = "";					       $form_data_array[32] = $form_block_score;		//Opt
        $form_cssurl = "";							   $form_data_array[33] = $form_cssurl;				//Opt
        $form_confirm_url = $this->getConfirmUrl();    $form_data_array[34] = $form_confirm_url;		//Req
        $form_cancel_url = $this->getCancelUrl();      $form_data_array[35] = $form_cancel_url;		    //Req
		$form_extXOrderId = "";					       $form_data_array[36] = $form_extXOrderId;			//Optional Parameter
		$form_extTokenOptions = "";			           $form_data_array[37] = $form_extTokenOptions;		//Optional Parameter
		$form_extToken = "";						    $form_data_array[38] = $form_extToken;				//Optional Parameter
		$form_var1 = "";								$form_data_array[39] = $form_var1;					//Optional Parameter
		$form_var2 = "";								$form_data_array[40] = $form_var2;					//Optional Parameter
		$form_var3 = "";								$form_data_array[41] = $form_var3;					//Optional Parameter
		$form_var4 = "";								$form_data_array[42] = $form_var4;					//Optional Parameter
		$form_var5 = "";								$form_data_array[43] = $form_var5;					//Optional Parameter
		$form_var6 = "";								$form_data_array[44] = $form_var6;					//Optional Parameter
		$form_var7 = "";								$form_data_array[45] = $form_var7;					//Optional Parameter
		$form_var8 = "";								$form_data_array[46] = $form_var8;					//Optional Parameter
		$form_var9 = "";								$form_data_array[47] = $form_var9;					//Optional Parameter
								                        $form_data_array[48] = $this->getConfig('privateKeyPass');

		ksort($form_data_array);

        foreach ($form_data_array as &$value) {
			if($value != ""){
				$form_data = $form_data . $value . ";";
			}
		}

		$form_data = implode("", $form_data_array);

		$digest = base64_encode(hash("sha256", $form_data, true));

        return '<form action="' . $this->getConfig('alphaUrl') . '" method="post" id="alphabank_payment_form">
                    <input type="hidden" name="version" value="'.$form_version.'"/>
                    <input type="hidden" name="mid" value="'.$form_mid.'"/>
                    <input type="hidden" name="lang" value="'.$form_lang .'"/>
                    <input type="hidden" name="deviceCategory" value="'.$form_device_cate .'"/>
                    <input type="hidden" name="orderid" value="'.$form_order_id .'"/>
                    <input type="hidden" name="orderDesc" value="'.$form_order_desc .'"/>
                    <input type="hidden" name="orderAmount" value="'.$form_order_amount .'"/>
                    <input type="hidden" name="currency" value="'.$form_currency .'"/>
                    <input type="hidden" name="payerEmail" value="'.$form_email .'"/>
                    <input type="hidden" name="payerPhone" value="'.$form_phone .'"/>
                    <input type="hidden" name="billCountry" value="'.$form_bill_country .'"/>
                    <input type="hidden" name="billState" value="'.$form_bill_state .'"/>
                    <input type="hidden" name="billZip" value="'.$form_bill_zip .'"/>
                    <input type="hidden" name="billCity" value="'.$form_bill_city .'"/>
                    <input type="hidden" name="billAddress" value="'.$form_bill_addr .'"/>
                    <input type="hidden" name="weight" value="'.$form_weight .'"/>
                    <input type="hidden" name="dimensions" value="'.$form_dimension .'"/>
                    <input type="hidden" name="shipCountry" value="'.$form_ship_counrty .'"/>
                    <input type="hidden" name="shipState" value="'.$form_ship_state .'"/>
                    <input type="hidden" name="shipZip" value="'.$form_ship_zip .'"/>
                    <input type="hidden" name="shipCity" value="'.$form_ship_city .'"/>
                    <input type="hidden" name="shipAddress" value="'.$form_ship_addr .'"/>
                    <input type="hidden" name="addFraudScore" value="'.$form_add_fraud_score .'"/>
                    <input type="hidden" name="maxPayRetries" value="'.$form_max_pay_retries .'"/>
                    <input type="hidden" name="reject3dsU" value="'.$form_reject3dsU .'"/>
                    <input type="hidden" name="payMethod" value="'.$form_pay_method .'"/>
                    <input type="hidden" name="trType" value="'.$form_trytpe .'"/>
                    <input type="hidden" name="extInstallmentoffset" value="'.$form_ext_install_offset .'"/>
                    <input type="hidden" name="extInstallmentperiod" value="'.$form_ext_install_period .'"/>
                    <input type="hidden" name="extRecurringfrequency" value="'.$form_ext_reccuring_freq .'"/>
                    <input type="hidden" name="extRecurringenddate" value="'.$form_ext_reccuring_enddate .'"/>
                    <input type="hidden" name="blockScore" value="'.$form_block_score .'"/>
                    <input type="hidden" name="cssUrl" value="'.$form_cssurl .'"/>
                    <input type="hidden" name="confirmUrl" value="'.$form_confirm_url .'"/>
                    <input type="hidden" name="cancelUrl" value="'.$form_cancel_url .'"/>
                    <input type="hidden" name="extXOrderId" value="'.$form_extXOrderId .'"/>
        			<input type="hidden" name="extTokenOptions" value="'.$form_extTokenOptions .'"/>
        			<input type="hidden" name="extToken" value="'.$form_extToken .'"/>
        			<input type="hidden" name="var1" value="'. $form_var1  .'"/>
        			<input type="hidden" name="var2" value="'. $form_var2 .'"/>
        			<input type="hidden" name="var3" value="'. $form_var3 .'"/>
        			<input type="hidden" name="var4" value="'. $form_var4 .'"/>
        			<input type="hidden" name="var5" value="'. $form_var5 .'"/>
        			<input type="hidden" name="var6" value="'. $form_var6 .'"/>
        			<input type="hidden" name="var7" value="'. $form_var7 .'"/>
        			<input type="hidden" name="var8" value="'. $form_var8 .'"/>
        			<input type="hidden" name="var9" value="'. $form_var9 .'"/>
        			<input type="hidden" name="digest" value="'. $digest .'"/>


					<script type="text/javascript">
                        document.getElementById("alphabank_payment_form").submit();
					</script>
				</form>';
    }
    /**
     * @return the $orderId
     */
    public function getOrderId ()
    {
        return $this->orderId;
    }

    /**
     * @param field_type $orderId
     */
    public function setOrderId ($orderId)
    {
        $this->orderId = $orderId;
    }
    /**
     * @return the $confirmUrl
     */
    public function getConfirmUrl ()
    {
        return $this->confirmUrl;
    }

    /**
     * @param field_type $url
     */
    public function setConfirmUrl ($url)
    {
        $this->confirmUrl = $url;
    }
    /**
     * @return the $cancelUrl
     */
    public function getCancelUrl ()
    {
        return $this->cancelUrl;
    }

    /**
     * @param field_type $cancelUrl
     */
    public function setCancelUrl ($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;
    }
}