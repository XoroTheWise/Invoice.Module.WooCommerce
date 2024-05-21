<?php

class RestClient
{
    public $url = "https://api.invoice.su/api/v2/";

    public $merchantKey;
    public $apiKey;

    /**
     * RestClient constructor.
     * @param $merchantKey
     * @param $apiKey
     */
    public function __construct($merchantKey, $apiKey)
    {
        $this->merchantKey = $merchantKey;
        $this->apiKey = $apiKey;
    }

    /**
     * @param $request_type
     * @param $json
     * @return bool|string
     */
    private function Send($request_type, $json)
    {
        $request = $this->url . $request_type;
        $auth = base64_encode($this->merchantKey . ":" . $this->apiKey);

        $options = array(
            'http' => array(
                'header'  => "Authorization: Basic ".$auth,
                'method'  => 'POST',
                'content' => $json
            )
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($request, false, $context);

        return $response;
    }

    /**
     * @param GET_TERMINAL $request
     * @return TerminalInfo
     */
    public function GetTerminal(GET_TERMINAL $request)
    {
        $response = $this->Send("GetTerminal", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param CREATE_TERMINAL $request
     * @return TerminalInfo
     */
    public function CreateTerminal(CREATE_TERMINAL $request)
    {
        $response = $this->Send("CreateTerminal", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param GET_REFUND $request
     * @return RefundInfo
     */
    public function GetRefund(GET_REFUND $request)
    {
        $response = $this->Send("GetRefund", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param CREATE_REFUND $request
     * @return RefundInfo
     */
    public function CreateRefund(CREATE_REFUND $request)
    {
        $response = $this->Send("CreateRefund", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param CLOSE_PAYMENT $request
     * @return PaymentInfo
     */
    public function ClosePayment(CLOSE_PAYMENT $request)
    {
        $response = $this->Send("ClosePayment", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param GET_PAYMENT_BY_ORDER $request
     * @return PaymentInfo
     */
    public function GetPaymentByOrder(GET_PAYMENT_BY_ORDER $request)
    {
        $response = $this->Send("GetPaymentByOrder", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param GET_PAYMENT $request
     * @return PaymentInfo
     */
    public function GetPayment(GET_PAYMENT $request)
    {
        $response = $this->Send("GetPayment", json_encode(get_object_vars($request)));
        return json_decode($response);
    }

    /**
     * @param CREATE_PAYMENT $request
     * @return PaymentInfo
     */
    public function CreatePayment(CREATE_PAYMENT $request)
    {
        $response = $this->Send("CreatePayment", json_encode(get_object_vars($request)));
        return json_decode($response);
    }
}
