<?php
/*
  Plugin Name: Invoice WC Integration
  Plugin URI:  http://invoice.su
  Description: Invoice WC integration
  Version: 1.0.0
  Author: Invoice LLC
 */

/**
 * Invoice Payment Gateway.
 *
 * @class       WC_Gateway_Invoice
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 */
require_once "sdk/RestClient.php";
require_once "sdk/CREATE_PAYMENT.php";
require_once "sdk/common/SETTINGS.php";
require_once "sdk/common/ORDER.php";
require_once "sdk/common/ITEM.php";
require_once "sdk/CREATE_TERMINAL.php";

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('plugins_loaded', 'invoice_gateway', 0);
function invoice_gateway()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_Gateway_Invoice'))
        return;

    class WC_Gateway_Invoice extends WC_Payment_Gateway
    {
        /**
         * @var RestClient $invoiceClient
         */
        private $invoiceClient;

        public function __construct()
        {
            $this->id = 'invoice';
            $this->has_fields = false;
            $this->order_button_text = __('Оплатить через Invoice', 'woocommerce');
            $this->method_title = __('Invoice', 'woocommerce');

            $this->method_description = __('Invoice integration for woocomerce.', 'woocommerce');
            $this->supports = array(
                'products',
                'refunds',
            );

            $this->init_settings();
            $this->init_form_fields();

            $this->title = "Invoice";
            $this->api_key = $this->get_option('api_key');
            $this->login = $this->get_option('login');
            $this->invoiceClient = new RestClient($this->login, $this->api_key);

            $terminal = $this->get_option("terminal");
            $terminal = str_replace("https://" ,"",$terminal);
            $terminal = str_replace("http://" ,"",$terminal);
            $terminal = explode("/",$terminal);
            $terminal = substr($terminal[1],1);
            $this->terminal = $terminal;

            if($terminal == null or $terminal == "") {
                $this->createTerminal();
            }

            add_action('woocommerce_receipt_invoice', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_order_status_processing', array($this, 'capture_payment'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));
        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(1)
            );

        }

        /**
         * @param string $order - WC order ID
         */
        public function receipt_page($order)
        {
            echo '<p>' . __('Спасибо за Ваш заказ!', 'invoice') . '</p>';
            echo $this->generate_form($order);
        }

        /**
         * @param string $order_id - WC order ID
         * @return string - Confirmation form
         */
        public function generate_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if($_POST["submit_invoice"] != null) {
                $paymentInfo = $this->createPayment($order);

                if($paymentInfo == null || $paymentInfo->error != null) {
                    return "Ошибка при создании заказа, попробуйте позже.";
                }

                header('Location: '.$paymentInfo->payment_url);
            }

            return
                '<form method="POST">'.
                '<input type="submit" class="button alt" name="submit_invoice" value="'.__('Оплатить', 'invoice').'" />
			        <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Отменить заказ', 'invoice').'</a>'."\n".
                '</form>';
        }

        public function createTerminal() {
            $create_terminal = new CREATE_TERMINAL();
            $create_terminal->type = "dynamical";
            $create_terminal->name = get_bloginfo('name');
            $create_terminal->description = get_bloginfo('description');
            $create_terminal->defaultPrice = 0;
            $terminal = $this->invoiceClient->CreateTerminal($create_terminal);

            $this->update_option("terminal", $terminal->link);
            $this->terminal = $terminal->id;
        }

        /**
         * Creating order on Invoice
         * @param WC_Order $order
         * @return PaymentInfo
         */
        public function createPayment($order) {
            $create_payment = new CREATE_PAYMENT();

            $settings = new SETTINGS();
            $settings->terminal_id = $this->terminal;
            $url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

            $settings->success_url = $url;
            $settings->fail_url = $url;
            $create_payment->settings = $settings;

            $invoice_order = new ORDER();
            $invoice_order->amount = $order->get_subtotal();
            $invoice_order->currency = $order->get_currency();
            $invoice_order->id = $order->get_id();
            $create_payment->order = $invoice_order;

            $receipt = array();
            foreach ($order->get_items() as $item) {
                $invoice_item = new ITEM();
                $invoice_item->name = $item->get_name();
                $invoice_item->price = $item->get_subtotal();
                $invoice_item->quantity = $item->get_quantity();
                $invoice_item->resultPrice = $item->get_subtotal();

                array_push($receipt, $invoice_item);
            }
            $create_payment->receipt = $receipt;

            return $this->invoiceClient->CreatePayment($create_payment);
        }

        /**
         * Admin fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'invoice'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'invoice'),
                    'default' => 'yes'
                ),
                'api_key' => array(
                    'title' => __('API Key', 'invoice'),
                    'type' => 'text',
                    'description' => __('Получить ключ вы можете в <a href="https://lk.invoice.su/">личном кабинете</a>', 'invoice'),
                    'default' => ''
                ),
                'login' => array(
                    'title' => __('Login', 'invoice'),
                    'type' => 'text',
                    'description' => __('Логин от личного кабинета', 'invoice'),
                    'default' => ''
                ),
            );
        }

        /**
         * Invoice callback function
         * @return string|void
         */
        public function callback() {
            $postData = file_get_contents('php://input');
            $notification = json_decode($postData, true);

            if(!isset($notification['id'])) {
                return;
            }

            $type = $notification["notification_type"];
            $id = $notification["order"]["id"];

            $signature = $notification["signature"];

            if($signature != $this->getSignature($notification["id"], $notification["status"], $this->api_key)) {
                return "Wrong signature";
            }

            $order = new WC_Order($id);
            if($type == "pay") {

                if($order->get_subtotal() > $notification["order"]["amount"]){
                    return "Wrong amount";
                }
                if($notification["status"] == "successful") {
                    $order->payment_complete();
                    return "payment successful";
                }
                if($notification["status"] == "error") {
                    $order->update_status('failed', $notification["status_description"]);
                    return "payment failed";
                }
            }
            if($type == "refund") {
                $order->update_status('refund', "Частичный возврат на сумму ".$notification["amount"]);
                return "OK";
            }
            return "null";
        }

        /**
         * @param string $id - Payment ID
         * @param string $status - Payment status
         * @param string $key - API Key
         * @return string Payment signature
         */
        public function getSignature($id, $status, $key) {
            return md5($id.$status.$key);
        }
    }

    /**
     * Adding gateway
     * @param $methods
     * @return array
     */
    function add_invoice_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Invoice';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_invoice_gateway');

}