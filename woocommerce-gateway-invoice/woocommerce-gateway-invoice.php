<?php
/**
 * Plugin Name: Invoice WC Integration
 * Plugin URI: https://github.com/Invoice-LLC/Invoice.Module.WooCommerce
 * Description: Платёжная система Invoice.
 * Version: 1.0.8
 *
 * Author: Invoice
 * Author URI: https://invoice.su/
 *
 * Text Domain: woocommerce-gateway-invoice
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 4.9
 *
 * Copyright: © 2019-2024 Invoice.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Invoice Payment gateway plugin class.
 *
 * @class WC_Invoice_Payments
 */
class WC_Invoice_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_invoice_woocommerce_block_support' ) );
	}

	/**
	 * Add the Invoice Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {
		$options = get_option( 'woocommerce_invoice_settings', array() );
		$gateways[] = 'WC_Gateway_Invoice';
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Make the WC_Gateway_Invoice class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-gateway-invoice.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_gateway_invoice_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-invoice-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Invoice_Blocks_Support() );
				}
			);
		}
	}
}

WC_Invoice_Payments::init();
