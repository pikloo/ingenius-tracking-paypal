<?php //phpcs:ignore

if ( ! class_exists( 'Ingenius_Tracking_Paypal_Admin' ) ) {
	/**
	 * Admin class for Ingenius Tracking Paypal plugin.
	 *
	 * This class handles the admin-related functionality for the Ingenius Tracking Paypal plugin.
	 *
	 * @package Ingenius_Tracking_Paypal
	 */
	class Ingenius_Tracking_Paypal_Admin {


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
		 * @param      string $plugin_name       The name of this plugin.
		 * @param      string $version    The version of this plugin.
		 */
		public function __construct( $plugin_name, $version ) {

			$this->plugin_name = $plugin_name;
			$this->version     = $version;
		}

		/**
		 * Register the stylesheets for the admin area.
		 */
		public function enqueue_styles() {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ingenius-tracking-paypal-admin.css', array(), $this->version, 'all' );
		}

		/**
		 * Register the JavaScript for the admin area.
		 */
		public function enqueue_scripts() {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ingenius-tracking-paypal-admin.js', array( 'jquery' ), $this->version, false );
		}


		/**
		 * Detects if the order has been manually saved
		 * Check if order exist and prevent the action from being executed only once
		 *
		 * @param int $order_id The id of current WooCommerce order.
		 */
		public function handle_order_save( int $order_id ): void {
			if ( ! $order_id || $this->order_updated ) {
				return;
			}

			if ( is_admin() && isset( $_POST['save'] ) ) //phpcs:ignore
			{
				$this->order_updated = true;
				$this->process_paypal_order_tracking( $order_id );
			}
		}


		/**
		 * Detects if the order has been import via WP All Import
		 *
		 * @param int $post_id The ID of the current post.
		 */
		public function handle_wp_all_import_order( int $post_id ): void {
			$post_type = get_post_type( $post_id );
			if ( 'shop_order_placehold' === $post_type ) {
				$this->process_paypal_order_tracking( $post_id, 'import' );
			}
		}

		/**
		 * Treats order tracking for order paid with PayPal
		 *
		 * @param int    $order_id The id of current order.
		 * @param string $mode The edition mode of the order.
		 */
		private function process_paypal_order_tracking( int $order_id, string $mode = 'edit' ): void {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-ingenius-tracking-paypal-order.php';

			new Ingenius_Tracking_Paypal_Order( $order_id, $mode );
		}
	}
}
