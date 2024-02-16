<?php

class RY_ECPay_Shipping_Meta_Box extends RY_WT_WC_Meta_Box
{
    public static function add_meta_box($post_type, $data_object): void
    {
        if ('shop_order' === $post_type || 'woocommerce_page_wc-orders' === $post_type) {
            $order = self::get_order_object($data_object);

            foreach ($order->get_items('shipping') as $item) {
                if (false !== RY_WT_WC_ECPay_Shipping::instance()->get_order_support_shipping($item)) {
                    add_meta_box('ry-ecpay-shipping-info', __('ECPay shipping info', 'ry-woocommerce-tools'), [__CLASS__, 'output'], $post_type, 'normal', 'default');
                    break;
                }
            }
        }
    }

    public static function output($data_object): void
    {
        include_once RY_WT_PLUGIN_DIR . 'woocommerce/shipping/ecpay/includes/shipping-list-table.php';

        $order = self::get_order_object($data_object);

        $list_table = new RY_ECPay_Shipping_Info_List_Table();
        $list_table->prepare_items($order);
        $list_table->display_action('ecpay');
        $list_table->display();
    }
}
