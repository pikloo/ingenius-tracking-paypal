<?php

namespace IngeniusTrackingPaypal\Admin;

defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Admin')) {
	class Ingenius_Tracking_Paypal_Admin
	{

		/**
		 * The ID of this plugin.
		 *
		 * @var      string    $plugin_name    The ID of this plugin.
		 */
		private $plugin_name;

		/**
		 * The version of this plugin.
		 *
		 * @var      string    $version    The current version of this plugin.
		 */
		private $version;

		/**
		 * Is the order as already updated
		 *
		 * @var boolean
		 */
		private $order_updated = false;

		/**
		 * Initialize the class and set its properties.
		 *
		 * @param      string    $plugin_name       The name of this plugin.
		 * @param      string    $version    The version of this plugin.
		 */
		public function __construct($plugin_name, $version)
		{

			$this->plugin_name = $plugin_name;
			$this->version = $version;
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 */
		public function enqueue_styles()
		{
			wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/ingenius-tracking-paypal-admin.css', array(), $this->version, 'all');
		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 */
		public function enqueue_scripts()
		{
			wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/ingenius-tracking-paypal-admin.js', array('jquery'), $this->version, false);
		}


		/**
		 * Detects if the order has been manually saved
		 * Check if order exist and prevent the action from being executed only once 
		 *
		 * @param mixed $order_id
		 * @return void
		 */
		public function it_handle_order_save($order_id)
		{
			if ( !$order_id || $this->order_updated) return;
			//TODO: trouver un moyen de lancer le traitement que si la modification ne vient qu'après avoir appuyer sur "mettre à jour"
			$this->order_updated = true;
			$this->it_woocommerce_aftership_order_paid($order_id);
		}


		/**
		 * Detects if the order has been import via WP All Import
		 *
		 * @param mixed $post_id
		 * @return void
		 */
		public function it_handle_wp_all_import_order($post_id)
		{
			$post_type = get_post_type($post_id);
			if ($post_type === 'shop_order_placehold') {
				$this->it_woocommerce_aftership_order_paid($post_id, 'import');
			}
		}

		/**
		 * Retrieve the information of an order managed by Aftership and paid by Paypal
		 *
		 * @param mixed $order_id
		 * @param string $mode
		 * @return void
		 */
		private function it_woocommerce_aftership_order_paid($order_id, $mode = 'edit')
		{
			require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-aftership-order.php';

			$aftership_order = new Ingenius_Tracking_Paypal_Aftership_Order($order_id, $mode);
			$order_datas  = $aftership_order->it_get_order_datas();

			if ($order_datas['payment_method'] === 'ppcp-gateway') {
				$aftership_order->it_send_tracking_to_paypal();
			}
		}
	}
}
