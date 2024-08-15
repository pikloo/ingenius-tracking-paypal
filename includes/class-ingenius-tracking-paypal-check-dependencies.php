<?php

class Ingenius_Tracking_Paypal_Check_Dependencies
{
    /**
     * Check if WooCommerce and WooCommerce Payment Paypal are enabled
     * If they are not enabled, uninstall the plugin and prevent the user from continuing by adding a back link
     */
    public static function check_dependencies()
    {
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            echo '<div class="error"><p>' . sprintf(__('<b>%s</b> requires WooCommerce. Please enable it to continue.', TEXT_DOMAIN), PLUGIN_NAME) . '</p></div>';
        }

        if (!is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')) {
            echo '<div class="error"><p>' . sprintf(__('<b>%s</b> requires WooCommerce Paypal Payments. Please enable it to continue.', TEXT_DOMAIN), PLUGIN_NAME) . '</p></div>';
            
            deactivate_plugins(plugin_basename(__FILE__));
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
}
