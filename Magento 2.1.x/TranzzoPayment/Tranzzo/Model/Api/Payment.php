<?php
namespace TranzzoPayment\Tranzzo\Model\Api;

class Payment
{
    CONST wrLog = false;

    /*
     * TRANZZO API
     * https://cdn.tranzzo.com/tranzzo-api/index.html
     */
    const P_MODE_HOSTED     = 'hosted';
    const P_MODE_DIRECT     = 'direct';

    //Primary operations
    const P_METHOD_PURCHASE = 'purchase';
    const P_METHOD_AUTH     = 'auth';

    const P_REQ_CPAY_ID     = 'uuid';
    const P_REQ_POS_ID      = 'pos_id';
    const P_REQ_ENDPOINT_KEY = 'key';
    const P_REQ_MODE        = 'mode';
    const P_REQ_METHOD      = 'method';
    const P_REQ_AMOUNT      = 'amount';
    const P_REQ_CURRENCY    = 'currency';
    const P_REQ_DESCRIPTION = 'description';
    const P_REQ_ORDER       = 'order_id';
    const P_REQ_PRODUCTS    = 'products';
    const P_REQ_ORDER_3DS_BYPASS   = 'order_3ds_bypass';
    const P_REQ_CC_NUMBER   = 'cc_number';
    const P_REQ_PAYWAY      = 'payway';

    const P_REQ_ORDER_AMOUNT      = 'order_amount';
    const P_REQ_ORDER_CURRENCY    = 'order_currency';

    const P_REQ_CHARGE_AMOUNT    = 'charge_amount';//amount to be captured
    const P_REQ_REFUND_AMOUNT    = 'refund_amount';//amount to be captured

    const P_OPT_PAYLOAD     = 'payload';

    const P_REQ_CUSTOMER_ID     = 'customer_id';
    const P_REQ_CUSTOMER_EMAIL  = 'customer_email';
    const P_REQ_CUSTOMER_FNAME  = 'customer_fname';
    const P_REQ_CUSTOMER_LNAME  = 'customer_lname';
    const P_REQ_CUSTOMER_PHONE  = 'customer_phone';
    const P_REQ_CUSTOMER_IP     = 'customer_ip';
    const P_REQ_CUSTOMER_COUNTRY = 'customer_country';

    const P_REQ_SERVER_URL  = 'server_url';
    const P_REQ_RESULT_URL  = 'result_url';

    const P_REQ_SANDBOX     = 'sandbox';

    //Response params
    const P_RES_METHOD      = 'method';
    const P_RES_PROV_ORDER  = 'provider_order_id';
    const P_RES_PAYMENT_ID  = 'payment_id';
    const P_RES_TRSACT_ID   = 'transaction_id';
    const P_RES_STATUS      = 'status';
    const P_RES_CODE        = 'code';
    const P_RES_RESP_CODE   = 'response_code';
    const P_RES_RESP_DESC   = 'response_description';
    const P_RES_ORDER       = 'order_id';
    const P_RES_AMOUNT      = 'amount';
    const P_RES_CURRENCY    = 'currency';


    //Statuses Transaction
    const STATUS_INIT = 'init';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';

    const TRZ_ST_INIT = 'init';
    const TRZ_ST_PENDING = 'pending';
    const TRZ_ST_PROCESSING = 'processing';
    const TRZ_ST_SUCCESS = 'success';
    const TRZ_ST_FAILURE = 'failure';


    //Request method
    const R_METHOD_GET  = 'GET';
    const R_METHOD_POST = 'POST';

    //URI method
    const U_METHOD_PAYMENT = 'payment';
    const U_METHOD_CAPTURE = 'capture';
    const U_METHOD_REFUND = 'refund';
    const U_METHOD_VOID = 'void';
    const U_METHOD_POS = 'pos';


    /**
     * @var string
     */
    private $apiUrl = 'https://cpay.tranzzo.com/api/v1';

    /**
     * @var string
     */
    private $posId;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $apiSecret;

    /**
     * @var string
     */
    private $endpointsKey;

    /**
     * @var array $headers
     */
    private $headers = [];

    private $params = [];

    /**
     * Ik_Service_Tranzzo_Api constructor.
     * @param $posId
     * @param $apiKey
     * @param $apiSecret
     * @param $endpointKey
     */
    public function __construct($posId, $apiKey, $apiSecret, $endpointKey)
    {
        if(empty($posId) || empty($apiKey) || empty($apiSecret) || empty($endpointKey)){
            self::writeLog('Invalid constructor parameters', '', 'error');
        }

        $this->posId = $posId;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->endpointsKey = $endpointKey;
    }

    public function setServerUrl($value = '')
    {
        $this->params[self::P_REQ_SERVER_URL] = $value;
    }

    public function setResultUrl($value = '')
    {
        $this->params[self::P_REQ_RESULT_URL] = $value;
    }

    public function setOrderId($value = '')
    {
        $this->params[self::P_REQ_ORDER] = strval($value);
    }

    public function setAmount($value = 0, $round = null)
    {
        $this->params[self::P_REQ_AMOUNT] = self::amountToDouble($value, $round);
    }

    public function setCurrency($value = '')
    {
        $this->params[self::P_REQ_CURRENCY] = $value;
    }

	/**
     * set for Void|Capture|Refund
     * Amount of original order
     * @param int $value
     * @param null $round
     */
    public function setOrderAmount($value = 0, $round = null)
    {
        $this->params[self::P_REQ_ORDER_AMOUNT] = self::amountToDouble($value, $round);
    }

	/**
     *  set for Void|Capture|Refund
     * Currency of original order
     * @param string $value
     */
    public function setOrderCurrency($value = '')
    {
        $this->params[self::P_REQ_ORDER_CURRENCY] = $value;
    }

	/**
     *  Optional amount to be captured set for Partial Capture
     * Currency of original order
     * @param string $value
     * @param null $round
     */
    public function setAmountPartialCapture($value = '', $round = null)
    {
        $this->params[self::P_REQ_CHARGE_AMOUNT] = self::amountToDouble($value, $round);
    }

	/**
     *  Amount to be refunded set for Partial refund
     * Currency of original order
     * @param string $value
     * @param null $round
     */
    public function setAmountPartialRefund($value = '', $round = null)
    {
        $this->params[self::P_REQ_REFUND_AMOUNT] = self::amountToDouble($value, $round);
    }

    public function setDescription($value = '')
    {
        $this->params[self::P_REQ_DESCRIPTION] = !empty($value)? $value : 'Order payment';
    }

    public function setCustomerId($value = '')
    {
        $this->params[self::P_REQ_CUSTOMER_ID] = !empty($value)? strval($value) : 'unregistered';
    }

    public function setCustomerEmail($value = '')
    {
        $this->params[self::P_REQ_CUSTOMER_EMAIL] = !empty($value)? strval($value) : 'unregistered';
    }

    public function setCustomerFirstName($value = '')
    {
        if(!empty($value))
            $this->params[self::P_REQ_CUSTOMER_FNAME] = $value;
    }

    public function setCustomerLastName($value = '')
    {
        if(!empty($value))
            $this->params[self::P_REQ_CUSTOMER_LNAME] = $value;
    }

    public function setCustomerPhone($value = '')
    {
        if(!empty($value))
            $this->params[self::P_REQ_CUSTOMER_PHONE] = $value;
    }

    public function setCustomerIp($value = '')
    {
        if(!empty($value))
            $this->params[self::P_REQ_CUSTOMER_IP] = $value;
    }

    public function setCustomerCountry($value = '')
    {
        if(!empty($value))
            $this->params[self::P_REQ_CUSTOMER_COUNTRY] = $value;
    }

    public function setProducts($value = array())
    {
        $this->params[self::P_REQ_PRODUCTS] = is_array($value)? $value : array();
    }

    public function addProduct($value = array())
    {
        if(is_array($value) && !empty($value))
            $this->params[self::P_REQ_PRODUCTS][] = $value;
    }

    /**
     * set custom value
     * @param string $value
     */
    public function setPayLoad($value = '')
    {
        //serialize_precision for json_encode
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $this->params[self::P_OPT_PAYLOAD] = is_array($value)? base64_encode(json_encode($value)) : $value;
    }

    public static function parsePayload($value = '')
    {
        if(!empty($value)) {
            $data = (base64_decode($value) === false) ? $value : base64_decode($value);

            return self::isJson($data)? json_decode($data, 1) : $data;
        }

        return $value;
    }

    public function setParam($key, $value = '')
    {
        $this->params[$key] = $value;
    }

    /**
     * @return array
     */
    public function getReqParams()
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function resetParams()
    {
        $this->params = [];
    }

    /**
     * @return mixed
     */
    public function createPaymentPurchase()
    {
        $this->params[self::P_REQ_POS_ID] = $this->posId;
        $this->params[self::P_REQ_MODE] = self::P_MODE_HOSTED;
//        $this->params[self::P_REQ_METHOD] = empty($method)? self::P_METHOD_PURCHASE : self::P_METHOD_AUTH;
        $this->params[self::P_REQ_METHOD] = self::P_METHOD_PURCHASE;
        $this->params[self::P_REQ_ORDER_3DS_BYPASS] = 'supported'; //supported(default) | always | never

        $this->setHeader('Accept: application/json');
        $this->setHeader('Content-Type: application/json');

        return $this->request(self::R_METHOD_POST, self::U_METHOD_PAYMENT);
    }

    public function createPaymentAuth()
    {
        $this->params[self::P_REQ_POS_ID] = $this->posId;
        $this->params[self::P_REQ_MODE] = self::P_MODE_HOSTED;
        $this->params[self::P_REQ_METHOD] = self::P_METHOD_AUTH;
        $this->params[self::P_REQ_ORDER_3DS_BYPASS] = 'supported';

        $this->setHeader('Accept: application/json');
        $this->setHeader('Content-Type: application/json');

        return $this->request(self::R_METHOD_POST, self::U_METHOD_PAYMENT);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function createCapture($params = [])
    {
        $params = !empty($params)? $params : $this->params;
        $params[self::P_REQ_POS_ID] = $this->posId;

//        $this->setHeader('Content-Type: application/json');

        return $this->request(self::R_METHOD_POST, self::U_METHOD_CAPTURE, $params);
    }

	/**
     * Void could be called only for authorizations with success status.
     * @param array $params
     * @return mixed
     */
    public function createVoid($params = [])
    {
        $params = !empty($params)? $params : $this->params;
        $params[self::P_REQ_POS_ID] = $this->posId;

        $this->setHeader('Accept: application/json');
        $this->setHeader('Content-Type: application/json');

        return $this->request(self::R_METHOD_POST, self::U_METHOD_VOID, $params);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function createRefund($params = [])
    {
        $params = !empty($params)? $params : $this->params;
        $params[self::P_REQ_POS_ID] = $this->posId;

        $this->setHeader('Accept: application/json');
        $this->setHeader('Content-Type: application/json');

        return $this->request(self::R_METHOD_POST, self::U_METHOD_REFUND, $params);
    }

	/**
     * Returns all transactions that are associated with order
     * @param string $orderId
     * @return mixed
     */
    public function getOrderTransactions($orderId = '')
    {
        $orderId = empty($orderId)? $this->params[self::P_REQ_ORDER] : $orderId;
        $uri = self::U_METHOD_POS. '/' . $this->posId . '/orders/' . $orderId;

        return $this->request(self::R_METHOD_GET, $uri, []);
    }

    /**
     * @param $params
     * @return mixed
     */
    private function request($method, $uri, $params = null)
    {
        $url    = $this->apiUrl . ((strpos(substr($uri, 0,1), '/') === false) ? '/' . $uri : $uri);
        $params = is_null($params)? $this->params : $params;

        //serialize_precision for json_encode
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $data = json_encode($params);

        if(json_last_error()) {
            self::writeLog(json_last_error(), 'json_last_error', 'error');
            self::writeLog(json_last_error_msg(), 'json_last_error_msg', 'error');
        }

        $this->setHeader('X-API-Auth: CPAY '.$this->apiKey.':'.$this->apiSecret);
        $this->setHeader('X-API-KEY: ' . $this->endpointsKey);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if($method === self::R_METHOD_POST){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $server_response = curl_exec($ch);
        $http_code = curl_getinfo($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

//         for check request
         self::writeLog($url);
         self::writeLog(['headers' => $this->headers]);
         self::writeLog(['params' => $params]);

         self::writeLog(["httpcode" => $http_code, "errno" => $errno]);
         self::writeLog(['response' => $server_response]);

        if(!$errno && empty($server_response))
            return $http_code;
        else
            return ((json_decode($server_response, true))? json_decode($server_response, true) : $server_response);
    }

    /**
     * @param $data
     * @param $requestSign
     * @return bool
     */
    public function validateSignature($data, $requestSign)
    {
        $signStr = $this->apiSecret . $data . $this->apiSecret;
        $sign = self::strToSign($signStr);

        if ($requestSign !== $sign) {
            return false;
        }

        return true;
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function parseDataResponse($data)
    {
        return json_decode(self::base64url_decode($data), true);
    }

    /**
     * @param $params
     * @return string
     */
    private function createSign($params)
    {
        //serialize_precision for json_encode
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set( 'serialize_precision', -1 );
        }
        $json      = self::base64url_encode( json_encode($params) );
        $signature = self::strToSign($this->apiSecret . $json . $this->apiSecret);
        return $signature;
    }

    /**
     * @param $str
     * @return string
     */
    private static function strToSign($str)
    {
        return self::base64url_encode(sha1($str, 1));
    }

    /**
     * @param $data
     * @return string
     */
    public static function base64url_encode($data)
    {
        return strtr(base64_encode($data), '+/', '-_');
    }
    /**
     * @param $data
     * @return bool|string
     */
    public static function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

	/**
     * @param $string
     * @return bool
     */
    public static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $header
     */
    private function setHeader($header)
    {
        if(!empty($header)) {
            if (!in_array($header, $this->headers)) {
                $this->headers[] = $header;
            }
        }
    }

    /**
     * @param $key
     * @return mixed
     */
    private function getHeader($key)
    {
        return $this->headers[$key];
    }

    /**
     * @param string $value
     * @param int $round
     * @return float
     */
    static function amountToDouble($value = '', $round = null)
    {
        $val = floatval($value);
        return is_null($round)? round($val, 2) : round($value, (int)$round);
    }

    /**
     * @param $data
     * @param string $flag
     * @param string $filename
     * @param bool|true $append
     */
    static function writeLog($data, $flag = '', $filename = '', $append = true)
    {
        if(self::wrLog) {
            $filename = !empty($filename) ? strval($filename) : 'requestResponseTRANZZO';

            //serialize_precision for json_encode
            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set( 'serialize_precision', -1 );
            }
            file_put_contents(__DIR__ . "/{$filename}.log", "\n\n" . date('Y-m-d H:i:s') . " - $flag \n" .
                (is_array($data) ? json_encode($data, JSON_PRETTY_PRINT) : $data)
                , ($append ? FILE_APPEND : 0)
            );
        }
    }
}