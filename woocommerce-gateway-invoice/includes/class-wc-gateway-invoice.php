<?php
/**
 * WC_Gateway_Invoice class
 *
 * @author   Invoice <dev@invoice.su>
 * @package  Invoice WC Integration
 * @since    1.0.0
 */

require_once "sdk/RestClient.php";
require_once "sdk/CREATE_PAYMENT.php";
require_once "sdk/CREATE_REFUND.php";
require_once "sdk/GET_PAYMENT_BY_ORDER.php";
require_once "sdk/common/SETTINGS.php";
require_once "sdk/common/REFUND_INFO.php";
require_once "sdk/common/ORDER.php";
require_once "sdk/common/ITEM.php";
require_once "sdk/CREATE_TERMINAL.php";
require_once "sdk/GET_TERMINAL.php";

// Выходим при попытке прямого доступа.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Gateway.
 *
 * @class    WC_Gateway_Invoice
 * @version  1.0.7
 */
class WC_Gateway_Invoice extends WC_Payment_Gateway {

	/**
	 * Payment gateway instructions.
	 * @var string
	 *
	 */
	protected $instructions;

	/**
	 * Whether the gateway is visible for non-admin users.
	 * @var boolean
	 *
	 */
	protected $hide_for_non_admin_users;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'invoice';
	public $apiKey;
	public $merchantKey;
	public $terminal;

	/**
    * @var RestClient $invoiceClient
    */
    private $invoiceClient;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		
		$this->icon               = apply_filters( 'woocommerce_invoice_gateway_icon', '' );
		$this->has_fields         = false;
		$this->supports           = array(
			'pre-orders',
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions'
		);
		$this->method_title       = _x( 'Оплата по QR-коду', '-Invoice', 'woocommerce-gateway-invoice' );
		$this->method_description = __( 'Безналичный расчёт Invoice.', 'woocommerce-gateway-invoice' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title                    = "Invoice";
		$this->description              = $this->get_option( 'description' );
		$this->apiKey					= $this->get_option( 'api_key' );
		$this->merchantKey				= $this->get_option( 'merchant_key' );
		$this->invoiceClient = new RestClient($this->merchantKey, $this->apiKey);

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_invoice', array( $this, 'process_subscription_payment' ), 10, 2 );
		add_action('init', array($this, 'register_webhook_endpoint'));
		add_action('template_redirect', array($this, 'handle_webhook_request'));
		// add_action( 'woocommerce_order_status_refunded', array( $this, 'process_order_refund' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( $this, 'process_order_refund' ), 10, 2 );

		register_activation_hook(__FILE__, array($this, 'flush_rewrite_rules_on_activation'));
		register_deactivation_hook(__FILE__, array($this, 'flush_rewrite_rules_on_deactivation'));
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-invoice' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Invoice Payments', 'woocommerce-gateway-invoice' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-invoice' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-invoice' ),
				'default'     => _x( 'Invoice Payment', 'Invoice payment method', 'woocommerce-gateway-invoice' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-invoice' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-gateway-invoice' ),
				'default'     => __( 'The goods are yours. No money needed.', 'woocommerce-gateway-invoice' ),
				'desc_tip'    => true,
			),
			'api_key' => array(
				'title'       => __( 'API Key', 'woocommerce-gateway-invoice' ),
				'type'        => 'text',
				'description' => __( 'Получить API-ключ вы можете в <a href="https://lk.invoice.su/settings?tab=general">личном кабинете</a>', 'woocommerce-gateway-invoice' ),
				'placeholder' => 'Введите ApiKey из личного кабинета invoice',
				'default'     => _x( '', '', 'woocommerce-gateway-invoice' ),
				'desc_tip'    => true,
			),
			'merchant_key' => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-invoice' ),
				'type'        => 'text',
				'placeholder' => 'Введите Merchant ID из личного кабинета invoice',
				'description' => __( 'Получить ID компании вы можете в <a href="https://lk.invoice.su/settings?tab=general">личном кабинете</a>', 'woocommerce-gateway-invoice' ),
				'default'     => _x( '', '', 'woocommerce-gateway-invoice' ),
				'desc_tip'    => true,
			)
		);
	}

	//ПОлучаем или создаём терминал
	public function getTerminal() {
		$site_url = get_option( 'siteurl' ); // URL сайта
		$admin_email = get_option( 'admin_email' ); // Email администратора
		$aliasId = md5( $site_url . ':' . $admin_email ); //Это id, который засуну в alias при создани терминала и чтобы получать его через getterminal
		
		$request = new GET_TERMINAL();
		$request->alias = $aliasId;
		$terminal = $this->invoiceClient->GetTerminal($request);
		
		if ($terminal->error != null) {
			$this->log("ERROR: ". json_encode($terminal). "\n");
			
			if ($terminal->error == 0){
				$message = __( 'Ошибка авторизации. Некорректный ApiKey или MerchantKey.', 'woocommerce-gateway-invoice' );
				throw new Exception( $message );
			} else {
				$terminal = $this->createTerminal($aliasId);
				return $terminal;
			}

		} else {
			$this->log("INFO: ". json_encode($terminal). "\n");
			return $terminal;
		}
		
		return null;
	}

	//метод срабатывает когда юзер нажимает оплатить
	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$terminal = $this->getTerminal();
		$order = wc_get_order( $order_id );

		$payment = $this->createPayment($order, $terminal);

		WC()->cart->empty_cart();
		
		return array(
			'result'    => 'success',
			'redirect'  => $payment
		);
	}

		//Формируем платёж
	public function createPayment($order, $terminal) {
		$create_payment = new CREATE_PAYMENT();

		//Добавляем натсройки
		$settings = new SETTINGS();
		$settings->terminal_id = $terminal;
		$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
		$settings->success_url = $url;
		$settings->fail_url = $url;
		$create_payment->settings = $settings;

		//Добавляем заказ
		$invoice_order = new ORDER();
		$invoice_order->amount = $order->get_total();
		$invoice_order->currency = $order->get_currency();
		$invoice_order->id = $order->id . "-" . md5(get_option('siteurl') . ':' . get_option('admin_email'));
		$create_payment->order = $invoice_order;

		//Добавляем позиции заказа
		$create_payment->receipt = $this->getReceipt($order);

		//Отправляем на создание
		$response = $this->invoiceClient->CreatePayment($create_payment);
		if ($response->error != null) {
			$this->log("ERROR: ". json_encode($response). "\n");
			$message = __( 'Ошибка создания платежа' . json_encode($response), 'woocommerce-gateway-invoice' );
			throw new Exception( $message );
		} else {
			$this->log("INFO: ". json_encode($response). "\n");
			return $response->payment_url;
		}

		return null;
	}

	//Получение итемов заказа
	public function getReceipt($order) {
		$receipt = array();
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$invoice_item = new ITEM();
			$invoice_item->name = $item->get_name();
			$invoice_item->price = $product->get_price();
			$invoice_item->quantity = $item->get_quantity();
			$invoice_item->resultPrice = $item->get_total();

			array_push($receipt, $invoice_item);
		}
		return $receipt;
	}

	//Создание терминала
	public function createTerminal($aliasId) {
		$create_terminal = new CREATE_TERMINAL();
		$create_terminal->name = get_bloginfo('name');
		$create_terminal->description = get_bloginfo('name') . " Terminal";
		$create_terminal->type = "dynamical";
		$create_terminal->defaultPrice = 0;
		$create_terminal->alias = $aliasId;
		
		$terminal = $this->invoiceClient->CreateTerminal($create_terminal);

		if ($terminal->error != null || $terminal == null){
			$this->log("ERROR: ". json_encode($terminal). "\n");
			$message = __( 'Ошибка создания терминала' . $terminal->description, 'woocommerce-gateway-invoice' );
			throw new Exception( $message );
		} else {
			$this->log("INFO: ". json_encode($terminal). "\n");
			return $terminal->id;
		}
		return null;
	}

	/**
	 * Process subscription payment.
	 *
	 * @param  float     $amount
	 * @param  WC_Order  $order
	 * @return void
	 */
	public function process_subscription_payment( $amount, $order ) {
		$payment_result = $this->get_option( 'result' );

		if ( 'success' === $payment_result ) {
			$order->payment_complete();
		} else {
			$message = __( 'Order payment failed. To make a successful payment using Invoice Payments, please review the gateway settings.', 'woocommerce-gateway-invoice' );
			throw new Exception( $message );
		}
	}

	//Регистрируем аддресс для вебхука "https://your-site.com/webhook-handler/"
	function register_webhook_endpoint() {
		add_rewrite_rule('^webhook-handler/?$', 'index.php?webhook-handler=1', 'top');
		add_rewrite_tag('%webhook-handler%', '([^&]+)');
	}

	//Обрабатываем запросы на вебхук
	function handle_webhook_request() {
		global $wp_query;
		if (isset($wp_query->query_vars['webhook-handler'])) {
			$this->process_webhook();
			exit;
		}
	}

	//Реализация логики вебхука и закрытие заказа
	public function process_webhook() {
		$body = @file_get_contents('php://input');
		$notification = json_decode($body, true);

		if(!isset($notification['id'])) {
			return;
		}

		if ($notification["status"] == "error"){
			$this->log( 'ERROR: ' . json_encode($notification) );
		}

		if (json_last_error() !== JSON_ERROR_NONE) {
			wp_die('Invalid JSON', 'Invalid JSON', 400);
		}

		$type = $notification["notification_type"];
		$order_id = strstr($notification["order"]["id"], "-", true);
		$signature = $notification["signature"];

		if($signature != $this->getSignature($notification["id"], $notification["status"], $this->apiKey)) {
			return "Wrong signature";
		}

		if (isset($order_id) && isset($notification['status'])) {
			if($type == "pay") {
				if ($notification['status'] === "successful") {
					$order = wc_get_order($order_id);
					if ($order) {
						$order->payment_complete();
						$order->update_status('completed');
						$order->add_order_note(__('Payment completed via webhook', 'woocommerce-gateway-invoice'));
						WC()->cart->empty_cart();
						status_header(200);
						exit;
					}
				}
			}
			if($type == "refund") {
				$order->update_status('refund', "Частичный возврат на сумму ".$notification["amount"]);
			}
		}

		wp_die('Webhook processed', 'Webhook processed', 200);
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

	//Обновляем правила перезаписи в wc
	function flush_rewrite_rules_on_activation() {
		$this->register_webhook_endpoint();
		flush_rewrite_rules();
	}

	function flush_rewrite_rules_on_deactivation() {
		flush_rewrite_rules();
	}

	//Реализация возврата. п.с Возврат и так сделается в wc, но нужно обработать через наше api
	public function process_order_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
	
		if ($order) {
			$refund = new CREATE_REFUND();
			$refund->id = $this->getPaymentId($order);
		
			$refund_info = new REFUND_INFO();
			$refund_info->amount = floatval(sanitize_text_field($_POST['refund_amount']));
			$refund_info->currency = $order->get_currency();
			$refund_info->reason = "Возврат Woocommerce Invoice";

			$refund->refund = $refund_info;
			$refund->receipt = $this->getReceipt($order);

			$response = $this->invoiceClient->CreateRefund($refund);
	
			if ( $response->status == "error" ) {
				$this->log( 'ERROR: ' . json_encode($response) );
				$order->update_status( 'processing' );
			} else {
				$order->add_order_note( __( 'Возврат обработан через API Invoice.', 'woocommerce-gateway-invoice' ) );
			}
		}
	}
	
	//Получаем id проведённого платежа
	public function getPaymentId($order) {
		$orderId = $order->id . "-" . md5(get_option('siteurl') . ':' . get_option('admin_email'));
		$request = new GET_PAYMENT_BY_ORDER($orderId);
		$response = $this->invoiceClient->GetPaymentByOrder($request);
	
		if ($response->status == "error"){
			$this->log("ERROR: ". json_encode($response). "\n");
			$message = __( 'Платёж обработался с ошибкой, возврат невозможен.', 'woocommerce-gateway-invoice' );
			throw new Exception( $message );
		}	

		return $response->id;
	}
	
	//Создание и запись лога в корень
	public function log($log) {
		$fp = fopen('invoice_payment.log', 'a+');
		fwrite($fp, $log);
		fclose($fp);
	}
}
