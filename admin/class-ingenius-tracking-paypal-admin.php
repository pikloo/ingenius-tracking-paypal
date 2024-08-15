<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Ingenius_Tracking_Paypal
 * @subpackage Ingenius_Tracking_Paypal/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Ingenius_Tracking_Paypal
 * @subpackage Ingenius_Tracking_Paypal/admin
 * @author     Your Name <email@example.com>
 */

class Ingenius_Tracking_Paypal_Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
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
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/ingenius-tracking-paypal-admin.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/ingenius-tracking-paypal-admin.js', array('jquery'), $this->version, false);
	}


	public function it_detect_order_status_completed($order_id)
	{
		// Vérifier que l'ID de la commande est valide
		if (!$order_id) {
			return;
		}
		$order = wc_get_order($order_id);
		// Vérifier que la commande a bien le statut 'completed'
		if ($order && $order->get_status() === 'completed') {
			$this->it_woocommerce_aftership_order_paid($order_id);
		}
	}

	/**
	 * Send to WooCommerce PayPal Payment the information of an order managed by Aftership and paid by Paypal
	 *
	 * @param mixed $order_id
	 * @return void
	 */
	private function it_woocommerce_aftership_order_paid($order_id)
	{
		require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-aftership-order.php';
		Ingenius_Tracking_Paypal_Aftership_Order::check_dependencies();

		$aftership_order = new Ingenius_Tracking_Paypal_Aftership_Order($order_id);

		$aftership_order->it_get_payment_method();
		// Vérifier si le mode de paiement est PayPal
		if ($aftership_order->it_get_payment_method() === 'paypal') {
			//Récupérer les données de tracking de la commande
			$aftership_order->it_register_order_datas();

			$tracking_number =  $aftership_order->get_tracking_number();
			$carrier_name =  $aftership_order->get_carrier_name();

			//TODO: Envoyer à Woocommerce paypal payment
		}
	}
}
