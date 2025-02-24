<?php //phpcs:ignore

if (! class_exists('Ingenius_Tracking_Paypal_Check_Dependencies')) {

    /**
     * Checks and verifies plugin dependencies for Ingenius Tracking Paypal.
     *
     * @package Ingenius_Tracking_Paypal
     */
    class Ingenius_Tracking_Paypal_Check_Dependencies
    {

        /**
         * Check if WooCommerce, WooCommerce Payment Paypal are enabled
         * If they are not enabled, uninstall the plugin and prevent the user from continuing by adding a back link
         */
        public static function check_dependencies()
        {
            $is_woocommerce_disabled                = ! is_plugin_active('woocommerce/woocommerce.php');
            $is_woocommerce_paypal_payment_disabled = ! is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php');
            // $is_aftership_disabled                  = ! is_plugin_active( 'aftership-woocommerce-tracking/aftership-woocommerce-tracking.php' );

            if ($is_woocommerce_disabled) {
                echo '<div class="error"><p>' . sprintf(esc_html__('<b>%s</b> requires WooCommerce. Please enable it to continue.', 'ingenius-tracking-paypal'), esc_html(PLUGIN_NAME)) . '</p></div>'; //phpcs:ignore
            }

            if ($is_woocommerce_paypal_payment_disabled) {
                echo '<div class="error"><p>' . sprintf(esc_html__('<b>%s</b> requires WooCommerce Paypal Payments. Please enable it to continue.', 'ingenius-tracking-paypal'), esc_html(PLUGIN_NAME)) . '</p></div>'; //phpcs:ignore
            }

            // if ( $is_aftership_disabled ) {
            // 	echo '<div class="error"><p>' . sprintf( esc_html__( '<b>%s</b> requires Aftership. Please enable it to continue.', 'ingenius-tracking-paypal' ), esc_html( PLUGIN_NAME ) ) . '</p></div>'; //phpcs:ignore
            // }

            if (
                $is_woocommerce_disabled
                || $is_woocommerce_paypal_payment_disabled
                // || $is_aftership_disabled 
            ) {
                deactivate_plugins(plugin_basename(__FILE__));
                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            }
        }
    }
}
