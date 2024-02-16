<?php

abstract class RY_WT_WC_Shipping_Method extends WC_Shipping_Method
{
    public $cost = 0;
    public $cost_requires = '';
    public $min_amount = 0;
    public $weight_plus_cost = 0;
    public $cost_offisland = 0;
    public $cost_cool = 0;

    public function init()
    {
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
        $this->cost = $this->get_option('cost');
        $this->cost_requires = $this->get_option('cost_requires');
        $this->min_amount = $this->get_option('min_amount', 0);
        $this->weight_plus_cost = $this->get_option('weight_plus_cost', 0);

        if (!wc_tax_enabled()) {
            unset($this->instance_form_fields['tax_status']);
        }

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);

        add_action('admin_footer', [$this, 'enqueue_admin_js'], 10);
    }

    public static function get_support_temp()
    {
        return ['1'];
    }

    public function get_instance_form_fields()
    {
        return parent::get_instance_form_fields();
    }

    public function is_available($package)
    {
        $available = $this->is_enabled();

        if($available) {
            $temps = $this->get_package_temp($package['contents']);
            $available = 0 === count(array_diff($temps, $this->get_support_temp()));
        }

        if($available) {
            $shipping_classes = WC()->shipping->get_shipping_classes();
            if (!empty($shipping_classes)) {
                $found_shipping_class = [];
                foreach ($package['contents'] as $values) {
                    if ($values['data']->needs_shipping()) {
                        $shipping_class_slug = $values['data']->get_shipping_class();
                        $shipping_class = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
                        if ($shipping_class && $shipping_class->term_id) {
                            $found_shipping_class[$shipping_class->term_id] = true;
                        }
                    }
                }
                foreach ($found_shipping_class as $shipping_class_term_id => $value) {
                    if ('yes' !== $this->get_option('class_available_' . $shipping_class_term_id, 'yes')) {
                        $available = false;
                        break;
                    }
                }
            }
        }

        if ($available) {
            return parent::is_available($package);
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this);
    }

    public function calculate_shipping($package = [])
    {
        $rate = [
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $this->cost,
            'package' => $package,
            'meta_data' => [
                'temps' => $this->get_package_temp($package['contents'])
            ]
        ];
        $rate = $this->add_rate_meta_data($rate);

        if ($this->cost_offisland > 0) {
            if (1 === (int) WC()->session->get('shipping_cvs_out_island')) {
                $rate['cost'] += $this->cost_offisland;
            }
        }

        if ($this->cost_cool > 0) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $temp = $cart_item['data']->get_meta('_ry_shipping_temp', true);
                if (empty($temp) && 'variation' === $cart_item['data']->get_type()) {
                    $parent_product = wc_get_product($cart_item['data']->get_parent_id());
                    $temp = $parent_product->get_meta('_ry_shipping_temp', true);
                }
                $temp = empty($temp) ? '1' : $temp;
                if ($temp !== '1') {
                    $rate['cost'] += $this->cost_cool;
                    break;
                }
            }
        }

        if ($rate['cost'] > 0) {
            $has_coupon = $this->check_has_coupon($this->cost_requires, ['coupon', 'min_amount_or_coupon', 'min_amount_and_coupon', 'min_amount_except_discount_or_coupon', 'min_amount_except_discount_and_coupon']);
            $has_min_amount = $this->check_has_min_amount($this->cost_requires, ['min_amount', 'min_amount_or_coupon', 'min_amount_and_coupon']);
            $has_min_amount_original = $this->check_has_min_amount($this->cost_requires, ['min_amount_except_discount', 'min_amount_except_discount_or_coupon', 'min_amount_except_discount_and_coupon'], true);

            switch ($this->cost_requires) {
                case 'coupon':
                    $set_cost_zero = $has_coupon;
                    break;
                case 'min_amount':
                    $set_cost_zero = $has_min_amount;
                    break;
                case 'min_amount_or_coupon':
                    $set_cost_zero = $has_min_amount || $has_coupon;
                    break;
                case 'min_amount_and_coupon':
                    $set_cost_zero = $has_min_amount && $has_coupon;
                    break;
                case 'min_amount_except_discount':
                    $set_cost_zero = $has_min_amount_original;
                    break;
                case 'min_amount_except_discount_or_coupon':
                    $set_cost_zero = $has_min_amount_original || $has_coupon;
                    break;
                case 'min_amount_except_discount_and_coupon':
                    $set_cost_zero = $has_min_amount_original && $has_coupon;
                    break;
                default:
                    $set_cost_zero = false;
                    break;
            }

            if ($set_cost_zero) {
                $rate['cost'] = 0;
            }

            if ($rate['cost'] > 0) {
                if ($this->weight_plus_cost > 0) {
                    $total_weight = WC()->cart->get_cart_contents_weight();
                    if ($total_weight > 0) {
                        $rate['cost'] *= (int) ceil($total_weight / $this->weight_plus_cost);
                    }
                }
            }
        }

        $this->add_rate($rate);
        do_action('woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate);
    }

    protected function get_package_temp($package_contents)
    {
        $temps = [];
        foreach ($package_contents as $values) {
            $temp = $values['data']->get_meta('_ry_shipping_temp', true);
            if (empty($temp) && 'variation' === $values['data']->get_type()) {
                $parent_product = wc_get_product($values['data']->get_parent_id());
                $temp = $parent_product->get_meta('_ry_shipping_temp', true);
            }
            $temps[] = empty($temp) ? '1' : $temp;
        }
        $temps = array_unique($temps);

        return $temps;
    }

    protected function add_rate_meta_data($rate)
    {
        return $rate;
    }

    protected function check_has_coupon($requires, $check_requires_list)
    {
        if (in_array($requires, $check_requires_list)) {
            $coupons = WC()->cart->get_coupons();
            if ($coupons) {
                foreach ($coupons as $code => $coupon) {
                    if ($coupon->is_valid() && $coupon->get_free_shipping()) {
                        return true;
                        break;
                    }
                }
            }
        }
        return false;
    }

    protected function check_has_min_amount($requires, $check_requires_list, $original = false)
    {
        if (in_array($requires, $check_requires_list)) {
            $total = WC()->cart->get_displayed_subtotal();
            if (false === $original) {
                if ('incl' === WC()->cart->get_tax_price_display_mode()) {
                    $total = round($total - (WC()->cart->get_cart_discount_total() + WC()->cart->get_cart_discount_tax_total()), wc_get_price_decimals());
                } else {
                    $total = round($total - WC()->cart->get_cart_discount_total(), wc_get_price_decimals());
                }
            }
            if ($total >= $this->min_amount) {
                return true;
            }
        }
        return false;
    }

    public function enqueue_admin_js()
    {
        static $is_print = [];
        if (is_admin()) {
            if (!isset($is_print[$this->id])) {
                $is_print[$this->id] = true;
                wc_enqueue_js(
                    'function ' . $this->id . '_MinAmountField(el) {
    let form = $(el).closest("form");
    let minAmountField = $("#woocommerce_' . $this->id . '_min_amount", form).closest("tr");
    if(minAmountField.length == 0) {
        minAmountField = $("#woocommerce_' . $this->id . '_min_amount", form).closest("fieldset");
    }
    switch( $(el).val() ) {
        case "min_amount":
        case "min_amount_or_coupon":
        case "min_amount_and_coupon":
        case "min_amount_except_discount":
        case "min_amount_except_discount_or_coupon":
        case "min_amount_except_discount_and_coupon":
            minAmountField.show();
            break;
        default:
            minAmountField.hide();
            break;
    }
}
$(document.body).on("change", "#woocommerce_' . $this->id . '_cost_requires", function(){
    ' . $this->id . '_MinAmountField(this);
}).change();
$(document.body).on("wc_backbone_modal_loaded", function(evt, target) {
    if("wc-modal-shipping-method-settings" === target ) {
        ' . $this->id . '_MinAmountField($("#wc-backbone-modal-dialog #woocommerce_' . $this->id . '_cost_requires", evt.currentTarget));
    }
});'
                );
            }
        }
    }
}
