<?php
defined('RY_WT_VERSION') OR exit('No direct script access allowed');

class RY_ECPay_Gateway_Credit_Installment extends RY_ECPay_Gateway_Base {
	public $payment_type = 'Credit';
	public $inpay_payment_type = 'CREDIT';

	public function __construct() {
		$this->id = 'ry_ecpay_credit_installment';
		$this->has_fields = false;
		$this->order_button_text = __('Pay via Credit(installment)', RY_WT::$textdomain);
		$this->method_title = __('ECPay Credit(installment)', RY_WT::$textdomain);
		$this->method_description = '';

		$this->form_fields = include(RY_WT_PLUGIN_DIR . 'woocommerce/gateways/ecpay/includes/settings-ecpay-gateway-credit-installment.php');
		$this->init_settings();

		$this->inpay = 'yes' == $this->get_option('inpay');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->min_amount = (int) $this->get_option('min_amount', 0);
		$this->number_of_periods = $this->get_option('number_of_periods', array());

		parent::__construct();
	}

	public function is_available() {
		if( 'yes' == $this->enabled && WC()->cart ) {
			if( empty($this->number_of_periods) ) {
				return false;
			}
			$total = WC()->cart->get_displayed_subtotal();
			if( 'incl' === WC()->cart->tax_display_cart ) {
				$total = round($total - (WC()->cart->get_discount_total() + WC()->cart->get_discount_tax()), wc_get_price_decimals());
			} else {
				$total = round($total - WC()->cart->get_discount_total(), wc_get_price_decimals());
			}

			if( $this->min_amount > 0 and $total < $this->min_amount ) {
				return false;
			}
		}

		return parent::is_available();
	}

	public function payment_fields() {
		parent::payment_fields();
		echo '<p>' . _x('Number of periods', 'Checkout info', RY_WT::$textdomain);
		echo ' <select name="number_of_periods">';
		foreach( $this->number_of_periods as $number_of_periods ) {
			echo '<option value="' . $number_of_periods . '">' . $number_of_periods . '</option>';
		}
		echo '</select>';
	}

	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		$order->add_order_note(__('Pay via ECPay Credit(installment)', RY_WT::$textdomain));
		if( isset($_POST['number_of_periods']) ) {
			$order->update_meta_data('_ecpay_payment_number_of_periods', (int) $_POST['number_of_periods']);
		}
		$order->save();
		wc_reduce_stock_levels($order_id);

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url(true),
		);
	}

	public function process_admin_options() {
		$this->check_inpay_with_ssl();

		parent::process_admin_options();
	}
}
