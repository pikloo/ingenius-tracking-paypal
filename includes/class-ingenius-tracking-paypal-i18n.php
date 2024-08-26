<?php

if (!class_exists('Ingenius_Tracking_Paypal_i18n')) {
	class Ingenius_Tracking_Paypal_i18n
	{


		/**
		 * Load the plugin text domain for translation.
		 *
		 * @since    1.0.0
		 */
		public function load_plugin_textdomain()
		{

			load_plugin_textdomain(
				'ingenius-tracking-paypal',
				false,
				dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
			);
		}
	}
}
