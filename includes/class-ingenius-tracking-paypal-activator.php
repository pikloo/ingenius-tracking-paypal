<?php

namespace IngeniusTrackingPaypal\Includes;

defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Activator')) {
class Ingenius_Tracking_Paypal_Activator
{
	/**
	 * Check if WooCommerce and WooCommerce Payment Paypal are enabled
	 */
	public static function activate()
	{
		if (!is_plugin_active('woocommerce/woocommerce.php')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(sprintf(__('<b>%s</b> requires WooCommerce. Please install and activate WooCommerce.', TEXT_DOMAIN), PLUGIN_NAME));
		}

		if (!is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(sprintf(__('<b>%s</b> requires WooCommerce Paypal Payments. Please install and activate WooCommerce Paypal Payments.', TEXT_DOMAIN), PLUGIN_NAME));
		}
	}
}
}