<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Layup_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'layup';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_layup_settings', []);
        $this->gateway = new WC_Layup_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles () {
        wp_register_script(
            'wc-layup-blocks-integration',
            plugin_dir_url(__FILE__) . '../js/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element', 
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        return [ 'wc-layup-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }
}
